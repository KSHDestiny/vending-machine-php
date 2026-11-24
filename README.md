# Vending Machine (Core PHP)

A vending machine system in plain PHP 8.4 (no framework) with MySQL 8.4, Apache
and Docker. Admins manage products and stock; customers buy products and see
their purchase history. The same rules are available as a JWT-secured REST API.

## Features

- **Products:** ID, name, price (`DECIMAL(10,3)`, so Pepsi stays `6.885`) and
  available quantity. Coke `3.99`, Pepsi `6.885` and Water `0.5` are seeded.
- **Authentication:** PHP sessions and `password_hash()` for the web; registration
  always creates a customer. The admin account comes from the seeder.
- **Role-based access:** checked on the server for every request. Only admins can
  manage products; only customers can buy.
- **Purchasing:** one database transaction locks the product row, checks stock,
  decrements it and records the purchase at the database price. A failure rolls
  everything back, and competing buyers cannot oversell.
- **Product list:** search, sorting by name, price or stock, and pagination
  (10 per page).
- **Attribute routing:** every route is a `#[Route]` attribute on its controller
  method, including `POST /products/{id}/purchase`.
- **Validation:** server-side for every input, mirrored in the browser. Price
  must be positive with up to 3 decimals; stock must be a whole number of 0 or
  more. Passwords need 8+ characters with upper and lowercase letters, a number
  and a symbol (at most 72 bytes).
- **Security:** CSRF tokens on browser forms, escaped HTML output, prepared
  statements, HttpOnly/SameSite session cookies and session renewal on login.

## Database design

| Table | Columns | Notes |
| --- | --- | --- |
| `users` | id, name, email (unique), password (hash), role (`admin`/`user`), timestamps | Plain passwords are never stored. |
| `products` | id, name, price `DECIMAL(10,3)`, quantity_available (unsigned), timestamps, deleted_at | Deleting a product is a soft delete, so purchase history keeps it. |
| `transactions` | id, user_id → users, product_id → products, quantity, unit_price, total_price, created_at | Stores the price at purchase time. Foreign keys restrict deletes; a check keeps `total_price = unit_price × quantity`. |

Migrations are in `database/migrations/` and seeders in `database/seeders/`.

## Project structure

```text
app/
  Controllers/        Web controllers (ProductsController, ...) and Api/ JSON controllers
  Services/           Business rules shared by web and API (purchase, products, auth)
  Repositories/       PDO queries behind interfaces (mocked in unit tests)
  Models/ Enums/      Product, User, Transaction, Role
  Http/ Middleware/   Request/response, attribute router, sessions, auth and CSRF
  Database/           PDO connection, transactions, query builder, migrator
bootstrap/app.php     Wires every object by constructor injection
routes/               Registers the controllers and names the middleware
resources/views/      PHP templates
public/index.php      The only public entry point
tests/                Unit/ (mocks, no database) and Integration/ (real MySQL)
```

## Run locally

Requires Docker with Compose.

```sh
cp .env.example .env            # then replace every placeholder value
docker compose up -d --build
docker compose exec app composer migrate
docker compose exec app composer seed
```

Open <http://localhost:10000>. `composer seed` adds the three products and the
`admin@example.com` account using `DEMO_ADMIN_PASSWORD` from `.env` (required).
If `DEMO_USER_PASSWORD` is set, it also adds the customer `user@example.com`.
Both passwords must meet the password rules above. Customers can also register.

`docker compose logs -f app db` shows startup errors. The first `up` also
creates the separate `vending_machine_test` database used by the integration
tests; it is created only when the MySQL volume is new.

## Web pages

| Who | Pages |
| --- | --- |
| Guests | `/login`, `/register` |
| Signed in | `/products` (search, sort, pages), `/products/{id}`, `/account` |
| Customers | Buy from `/products/{id}`, see `/my-transactions` and each confirmation |
| Admins | `/admin` dashboard, `/admin/products` (add, edit, delete), `/admin/transactions` |

## REST API

Send `Authorization: Bearer <token>` with the token returned by register or
login. Tokens are HS256 JWTs signed with `JWT_SECRET` and expire after one hour;
the user's role is reloaded from the database on every request.

| Method | Path | Access |
| --- | --- | --- |
| `POST` | `/api/register`, `/api/login` | Public; returns a token |
| `GET` | `/api/products?q=&sort=&direction=&page=`, `/api/products/{id}` | Any token |
| `POST` | `/api/products` | Admin |
| `PUT`, `DELETE` | `/api/products/{id}` | Admin |
| `POST` | `/api/products/{id}/purchase` with `{"quantity": 2}` | Customer |
| `GET` | `/api/my-transactions` | Customer |
| `GET` | `/api/transactions` | Admin |
| `GET`, `PUT` | `/api/me` (name), `PUT /api/me/password` | Any token |

Errors are JSON with the matching status: 401, 403, 404, 405 or 422 (with an
`errors` object per field).

```sh
TOKEN=$(curl -s localhost:10000/api/login -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"<password>"}' | jq -r .data.token)
curl -s localhost:10000/api/products/2/purchase -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -d '{"quantity":1}'
```

## Tests and static analysis

```sh
docker compose exec app composer test:unit       # 54 unit tests, no database
docker compose exec app composer analyse         # PHPStan
docker compose exec app composer test            # unit + integration tests
```

- **Unit tests** send requests through the real routes, middleware and
  controllers with mocked repositories (dependency injection), so they need no
  database. They cover product CRUD, admin-only access, CSRF, validation, prices
  taken from the database, JWT and the router.
- **Integration tests** run purchases against MySQL: insufficient stock changes
  nothing, a failed recording rolls back the stock, and six buyers racing for
  the last item produce exactly one sale.

> **Warning:** the integration tests empty the tables of the database named by
> `DB_TEST_NAME` before each test. They refuse to run unless `DB_NAME` is set
> and `DB_TEST_NAME` is a different database whose name ends in `_test`. Never
> point `DB_TEST_NAME` at a database with data you want to keep.

## Railway deployment

Use one Railway project with a PHP web service (this repository's `Dockerfile`)
and a MySQL database service. Keep MySQL private; do not enable its public TCP
proxy for the web app.

1. Add MySQL to the project, then add a web service from this repository.
   Set the web service's start command to `sh bin/start.sh` and its health check
   path to `/health`. The script waits up to 60 seconds for a sleeping database
   to wake, then migrates and seeds before starting Apache.
2. In the web service's Variables, reference the MySQL service's private values
   (replace `MySQL` if the service has a different name):

   | App variable | Railway value |
   | --- | --- |
   | `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
   | `DB_PORT` | `${{MySQL.MYSQLPORT}}` |
   | `DB_NAME` | `${{MySQL.MYSQLDATABASE}}` |
   | `DB_USER` | `${{MySQL.MYSQLUSER}}` |
   | `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |

3. Set `APP_ENV=production`, a unique random `JWT_SECRET` (32+ bytes), and a
   `SEED_ADMIN_PASSWORD` that meets the password rules, as private service
   variables. Never commit these values. Add a public domain to the web service,
   check `/health`, and sign in as `admin@example.com`.

The Free plan's $1 monthly usage credit covers both services and the database
volume; services sleep when idle and stop if the credit is exhausted.

## Known limitations

- Users cannot be deleted, and purchase records cannot be edited or deleted:
  purchases are kept as an audit history. Products use soft deletes.
- Sessions are stored as files in the web container, so a restart signs users
  out. API tokens cannot be revoked before they expire.
