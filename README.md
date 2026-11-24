# Vending Machine (Core PHP)

PHP 8.4, Apache and MySQL 8.4. Products and purchase history require login;
registration creates customer accounts only.

## Railway deployment

Use one Railway project with a PHP web service (this repository's `Dockerfile`)
and a MySQL database service. Keep MySQL private; do not enable its public TCP
proxy for the web app.

1. Add MySQL to the project, then add a web service from the complete repository.
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
   `SEED_ADMIN_PASSWORD` (8+ characters with upper/lowercase letters, a number
   and a symbol; at most 72 UTF-8 bytes) as private service variables.
   Never commit these values. Add a public domain to the web service, check
   `/health`, and sign in as `admin@example.com`. Customers can register.

The Free plan's $1 monthly usage credit covers both services and the database
volume; services sleep when idle and stop if the credit is exhausted. PHP
sessions stored on the web container are lost on restarts. Do not deploy until
the complete application is committed and the deployment plan is reviewed.
