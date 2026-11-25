<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Enums\Role;
use App\Http\OpenApi;
use App\Http\Routing\Route;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Page;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Support\Application;

/*
 * The API documentation must match the code: every /api route is documented with its
 * methods and security, nothing is documented that doesn't exist, and the docs pages work.
 */
final class OpenApiTest extends TestCase
{
    public function testEveryApiRouteIsDocumentedAndNothingElse(): void
    {
        $documented = [];
        foreach (OpenApi::document()['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                $documented[] = strtoupper((string) $method) . ' ' . $path;
            }
        }
        sort($documented);

        self::assertSame(array_keys(self::apiRoutes()), $documented);
    }

    public function testSecurityMatchesEachRoutesMiddleware(): void
    {
        $document = OpenApi::document();

        foreach (self::apiRoutes() as $route => $middleware) {
            [$method, $path] = explode(' ', $route, 2);
            $operation = $document['paths'][$path][strtolower($method)];
            $secured = ($operation['security'] ?? null) === [['bearerAuth' => []]];

            self::assertSame($middleware !== [], $secured, "{$route} security");
            if ($secured) {
                self::assertArrayHasKey('401', $operation['responses'], "{$route} documents 401");
            }
            if (in_array('jwt.admin', $middleware, true) || in_array('jwt.user', $middleware, true)) {
                self::assertArrayHasKey('403', $operation['responses'], "{$route} documents 403");
            }
        }
    }

    public function testDocumentedFieldsAreTheFieldsTheApiSends(): void
    {
        $schemas = OpenApi::document()['components']['schemas'];
        $fields = static fn (string $schema): array => array_keys($schemas[$schema]['properties']);
        $customer = new User(2, 'Buyer', 'buyer@example.com', password_hash('User#Pass1', PASSWORD_DEFAULT), Role::User);
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findByEmail')->willReturn($customer);
        $login = Application::json((new Application(['users' => $users]))->api('POST', '/api/login', null, ['email' => 'buyer@example.com', 'password' => 'User#Pass1']));

        self::assertSame($fields('User'), array_keys($customer->toArray()));
        self::assertSame($fields('Product'), array_keys((new Product(2, 'Pepsi', '6.885', 4))->toArray()));
        self::assertSame($fields('Transaction'), array_keys((new Transaction(7, 2, 2, 2, '6.885', '13.770', '2026-11-25 10:00:00', 'Pepsi', 'Buyer', 'buyer@example.com'))->toArray()));
        self::assertSame($fields('Meta'), array_keys((new Page([], 0, 1, 10))->meta()));
        self::assertSame($fields('TokenResponse'), array_keys((array) $login['data']));
    }

    public function testTheSpecAndTheDocsPageAreServed(): void
    {
        $app = new Application();

        $spec = $app->send('GET', '/openapi.json');
        $docs = $app->send('GET', '/docs');

        self::assertSame(200, $spec->status);
        self::assertSame('3.0.3', Application::json($spec)['openapi']);
        self::assertSame(200, $docs->status);
        self::assertStringContainsString('url: "/openapi.json"', $docs->body);
        self::assertSame(2, preg_match_all('/integrity="sha384-[A-Za-z0-9+\/=]{64}" crossorigin="anonymous"/', $docs->body), 'both CDN files are pinned with SRI');
        self::assertStringContainsString('swagger-ui-dist@5.35.0', $docs->body);
    }

    /**
     * "METHOD /path" => middleware for every #[Route] under /api, sorted.
     * @return array<string, list<string>>
     */
    private static function apiRoutes(): array
    {
        $routes = [];
        foreach (glob(dirname(__DIR__, 3) . '/app/Controllers/Api/*.php') ?: [] as $file) {
            $name = 'App\\Controllers\\Api\\' . basename($file, '.php');
            if (!class_exists($name)) {
                continue;
            }
            $class = new ReflectionClass($name);
            foreach ($class->getMethods() as $method) {
                foreach ($method->getAttributes(Route::class) as $attribute) {
                    $route = $attribute->newInstance();
                    foreach ($route->methods as $verb) {
                        $routes["{$verb} {$route->path}"] = $route->middleware;
                    }
                }
            }
        }
        ksort($routes);

        return $routes;
    }
}
