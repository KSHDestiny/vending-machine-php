<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\Validator;

/*
 * The OpenAPI 3 description of the JSON API, served at /openapi.json and shown at /docs.
 * Limits come from the same constants the validation uses, and OpenApiTest checks that
 * every /api route is documented with the right security, so the docs cannot drift.
 */
final class OpenApi
{
    /**
     * The whole document.
     * @return array<string, mixed>
     */
    public static function document(): array
    {
        $bearer = [['bearerAuth' => []]];
        $id = ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer', 'minimum' => 1]];
        $page = ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]];

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Vending Machine API',
                'version' => '1.0.0',
                'description' => "JSON API for the vending machine.\n\n"
                    . "**To call protected endpoints:** run `POST /api/login`, copy the `token` from the response, click **Authorize**, paste "
                    . "only the token (no quotes, no `Bearer ` prefix), click **Authorize** in the dialog, then **Close**. The padlocks then show "
                    . "as closed, which means your requests send the token. Tokens last one hour.\n\n"
                    . "Customers buy products; admins manage them. Prices are exact decimal strings with up to "
                    . "3 decimals (e.g. `\"6.885\"`), never floats. Errors are `{\"message\": ...}`; validation errors (422) add `errors` per field.",
            ],
            'servers' => [['url' => '/']],
            'tags' => [
                ['name' => 'Auth', 'description' => 'Register, log in and manage your own account'],
                ['name' => 'Products', 'description' => 'The catalogue; customers see only products on sale'],
                ['name' => 'Purchases', 'description' => 'Buying and purchase history'],
            ],
            'paths' => [
                '/api/register' => ['post' => self::op('Auth', 'Create a customer account', 'Always creates a customer, never an admin. Returns a token, like login.', null, [
                    'requestBody' => self::body('RegisterRequest'),
                    'responses' => ['201' => self::data('Account created', 'TokenResponse')] + self::errors(422),
                ])],
                '/api/login' => ['post' => self::op('Auth', 'Log in', 'Returns a bearer token valid for one hour. Deactivated accounts get the same 401 as a wrong password.', null, [
                    'requestBody' => self::body('LoginRequest'),
                    'responses' => ['200' => self::data('Logged in', 'TokenResponse')] + self::errors(401, 422),
                ])],
                '/api/me' => [
                    'get' => self::op('Auth', 'Your account', 'Any signed-in user.', $bearer, [
                        'responses' => ['200' => self::data('The token\'s user', 'User')] + self::errors(401),
                    ]),
                    'put' => self::op('Auth', 'Change your name', 'Only the name; email and role cannot change here.', $bearer, [
                        'requestBody' => self::body('NameRequest'),
                        'responses' => ['200' => self::data('Updated', 'User')] + self::errors(401, 422),
                    ]),
                ],
                '/api/me/password' => ['put' => self::op('Auth', 'Change your password', 'Needs the current password. Also signs you out of every web session.', $bearer, [
                    'requestBody' => self::body('PasswordRequest'),
                    'responses' => ['204' => ['description' => 'Changed']] + self::errors(401, 422),
                ])],
                '/api/products' => [
                    'get' => self::op('Products', 'List products', 'Paged, 10 per page. Customers see only products on sale; admins see all.', $bearer, [
                        'parameters' => [
                            $page,
                            ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['name', 'price', 'quantity_available'], 'default' => 'name']],
                            ['name' => 'direction', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc']],
                            ['name' => 'q', 'in' => 'query', 'description' => 'Part of the name', 'schema' => ['type' => 'string', 'maxLength' => 100]],
                        ],
                        'responses' => ['200' => self::page('A page of products', 'Product')] + self::errors(401),
                    ]),
                    'post' => self::op('Products', 'Create a product', 'Admins only. Its starting stock is recorded in the stock history.', $bearer, [
                        'requestBody' => self::body('ProductRequest'),
                        'responses' => ['201' => self::data('Created (Location header points to it)', 'Product')] + self::errors(401, 403, 422),
                    ]),
                ],
                '/api/products/{id}' => [
                    'get' => self::op('Products', 'Get a product', 'Products off sale are 404 for customers.', $bearer, [
                        'parameters' => [$id],
                        'responses' => ['200' => self::data('The product', 'Product')] + self::errors(401, 404),
                    ]),
                    'put' => self::op('Products', 'Update a product', 'Admins only. A changed quantity is recorded as an adjustment; status is optional.', $bearer, [
                        'parameters' => [$id],
                        'requestBody' => self::body('ProductUpdateRequest'),
                        'responses' => ['200' => self::data('Updated', 'Product')] + self::errors(401, 403, 404, 422),
                    ]),
                    'delete' => self::op('Products', 'Delete a product', 'Admins only. A soft delete: purchases keep it.', $bearer, [
                        'parameters' => [$id],
                        'responses' => ['204' => ['description' => 'Deleted']] + self::errors(401, 403, 404),
                    ]),
                ],
                '/api/products/{id}/purchase' => ['post' => self::op('Purchases', 'Buy a product', 'Customers only. The price always comes from the database, and the stock is checked and taken in one locked transaction, so two buyers can never take the same last item.', $bearer, [
                    'parameters' => [$id],
                    'requestBody' => self::body('PurchaseRequest'),
                    'responses' => ['201' => self::data('Bought', 'Transaction')] + self::errors(401, 403, 404, 422),
                ])],
                '/api/my-transactions' => ['get' => self::op('Purchases', 'Your purchases', 'Customers only. Newest first, 10 per page.', $bearer, [
                    'parameters' => [$page],
                    'responses' => ['200' => self::page('A page of your purchases', 'Transaction')] + self::errors(401, 403),
                ])],
                '/api/transactions' => ['get' => self::op('Purchases', 'All purchases', 'Admins only. Newest first, 10 per page, with each buyer\'s email.', $bearer, [
                    'parameters' => [$page],
                    'responses' => ['200' => self::page('A page of purchases', 'Transaction')] + self::errors(401, 403),
                ])],
            ],
            'components' => [
                'securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT']],
                'schemas' => self::schemas(),
            ],
        ];
    }

    /**
     * The reusable schemas.
     * @return array<string, mixed>
     */
    private static function schemas(): array
    {
        $money = ['type' => 'string', 'pattern' => '^\d{1,7}(\.\d{1,3})?$', 'description' => 'Exact decimal, up to 3 decimals, above 0 and at most ' . Validator::MAX_AMOUNT];
        $password = ['type' => 'string', 'format' => 'password', 'minLength' => 8, 'description' => 'At least 8 characters (at most 72 bytes), with upper and lower case, a digit and a symbol'];

        return [
            'User' => self::object(['id' => ['type' => 'integer', 'example' => 2], 'name' => ['type' => 'string', 'example' => 'Demo User'], 'email' => ['type' => 'string', 'format' => 'email', 'example' => 'user@example.com'], 'role' => ['type' => 'string', 'enum' => ['admin', 'user']]]),
            'TokenResponse' => self::object(['token' => ['type' => 'string', 'description' => 'Send as "Authorization: Bearer <token>"'], 'token_type' => ['type' => 'string', 'example' => 'Bearer'], 'expires_in' => ['type' => 'integer', 'example' => 3600], 'user' => ['$ref' => '#/components/schemas/User']]),
            'Product' => self::object(['id' => ['type' => 'integer', 'example' => 2], 'name' => ['type' => 'string', 'example' => 'Pepsi'], 'price' => $money + ['example' => '6.885'], 'quantity_available' => ['type' => 'integer', 'minimum' => 0, 'example' => 100], 'status' => ['type' => 'string', 'enum' => ['active', 'inactive'], 'description' => 'inactive = off sale']]),
            'Transaction' => self::object([
                'id' => ['type' => 'integer', 'example' => 7], 'user_id' => ['type' => 'integer'], 'product_id' => ['type' => 'integer'],
                'product_name' => ['type' => 'string', 'description' => 'The name at the time of purchase', 'example' => 'Pepsi'],
                'quantity' => ['type' => 'integer', 'example' => 2], 'unit_price' => $money + ['example' => '6.885'], 'total_price' => $money + ['example' => '13.770'],
                'created_at' => ['type' => 'string', 'example' => '2026-11-25 10:00:00'], 'user_email' => ['type' => 'string', 'format' => 'email'],
            ], ['id', 'user_id', 'product_id', 'quantity', 'unit_price', 'total_price', 'created_at']),
            'Meta' => self::object(['page' => ['type' => 'integer'], 'per_page' => ['type' => 'integer', 'example' => 10], 'total' => ['type' => 'integer'], 'last_page' => ['type' => 'integer']]),
            'Error' => self::object(['message' => ['type' => 'string'], 'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'string'], 'description' => 'Only on 422: field => message']], ['message']),
            'RegisterRequest' => self::object(['name' => ['type' => 'string', 'maxLength' => 100], 'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 255], 'password' => $password, 'password_confirmation' => ['type' => 'string', 'format' => 'password']]),
            'LoginRequest' => self::object(['email' => ['type' => 'string', 'format' => 'email', 'example' => 'user@example.com'], 'password' => ['type' => 'string', 'format' => 'password']]),
            'NameRequest' => self::object(['name' => ['type' => 'string', 'maxLength' => 100]]),
            'PasswordRequest' => self::object(['current_password' => ['type' => 'string', 'format' => 'password'], 'password' => $password, 'password_confirmation' => ['type' => 'string', 'format' => 'password']]),
            'ProductRequest' => self::object(['name' => ['type' => 'string', 'maxLength' => 100, 'example' => 'Green Tea'], 'price' => $money + ['example' => '1.250'], 'quantity_available' => ['type' => 'integer', 'minimum' => 0, 'example' => 20]]),
            'ProductUpdateRequest' => self::object(['name' => ['type' => 'string', 'maxLength' => 100], 'price' => $money, 'quantity_available' => ['type' => 'integer', 'minimum' => 0], 'status' => ['type' => 'string', 'enum' => ['active', 'inactive'], 'description' => 'Optional; leave out to keep the current one']], ['name', 'price', 'quantity_available']),
            'PurchaseRequest' => self::object(['quantity' => ['type' => 'integer', 'minimum' => 1, 'example' => 2]]),
        ];
    }

    /**
     * An operation with its tag, summary, description and security (null = public).
     * @param list<array<string, list<string>>>|null $security
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private static function op(string $tag, string $summary, string $description, ?array $security, array $details): array
    {
        return ['tags' => [$tag], 'summary' => $summary, 'description' => $description] + ($security === null ? [] : ['security' => $security]) + $details;
    }

    /**
     * A required JSON request body.
     * @return array<string, mixed>
     */
    private static function body(string $schema): array
    {
        return ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$schema}"]]]];
    }

    /**
     * A {"data": <schema>} response.
     * @return array<string, mixed>
     */
    private static function data(string $description, string $schema): array
    {
        return ['description' => $description, 'content' => ['application/json' => ['schema' => self::object(['data' => ['$ref' => "#/components/schemas/{$schema}"]])]]];
    }

    /**
     * A {"data": [<schema>], "meta": ...} response.
     * @return array<string, mixed>
     */
    private static function page(string $description, string $schema): array
    {
        return ['description' => $description, 'content' => ['application/json' => ['schema' => self::object([
            'data' => ['type' => 'array', 'items' => ['$ref' => "#/components/schemas/{$schema}"]],
            'meta' => ['$ref' => '#/components/schemas/Meta'],
        ])]]];
    }

    /**
     * Error responses for these status codes (PHP keeps numeric keys as integers; JSON writes them as strings).
     * @return array<int, array<string, mixed>>
     */
    private static function errors(int ...$codes): array
    {
        $descriptions = [401 => 'Missing, invalid or expired token, or wrong credentials', 403 => 'Signed in, but this role may not do this', 404 => 'Not found', 422 => 'Invalid input; errors lists each field'];
        $responses = [];
        foreach ($codes as $code) {
            $responses[(string) $code] = ['description' => $descriptions[$code], 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]];
        }

        return $responses;
    }

    /**
     * An object schema; every property is required unless $required lists only some.
     * @param array<string, mixed> $properties
     * @param list<string>|null $required
     * @return array<string, mixed>
     */
    private static function object(array $properties, ?array $required = null): array
    {
        return ['type' => 'object', 'required' => $required ?? array_keys($properties), 'properties' => $properties];
    }
}
