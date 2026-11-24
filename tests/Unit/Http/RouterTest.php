<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Exceptions\MethodNotAllowedException;
use App\Exceptions\NotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Routing\Route;
use App\Http\Routing\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testAttributeRoutePassesIntPlaceholdersAsInts(): void
    {
        $router = new Router();
        $router->registerController(new class {
            #[Route('/products/{id}/purchase', ['POST'])]
            public function purchase(Request $request, int $id): Response
            {
                return new Response(200, 'bought ' . get_debug_type($id) . " {$id}");
            }
        });

        self::assertSame('bought int 3', $router->dispatch(new Request('POST', '/products/3/purchase'))->body);
    }

    public function testNonNumericIdDoesNotMatchAnIntPlaceholder(): void
    {
        $router = new Router();
        $router->registerController(new class {
            #[Route('/products/{id}')]
            public function show(Request $request, int $id): Response
            {
                return new Response(200);
            }
        });

        $this->expectException(NotFoundException::class);
        $router->dispatch(new Request('GET', '/products/abc'));
    }

    public function testIntPlaceholderAcceptsBigIdsButNotOnesBeyondPhpInt(): void
    {
        $router = new Router();
        $router->registerController(new class {
            #[Route('/transactions/{id}')]
            public function show(Request $request, int $id): Response
            {
                return new Response(200, "transaction {$id}");
            }
        });

        self::assertSame('transaction 12345678901', $router->dispatch(new Request('GET', '/transactions/12345678901'))->body);
        self::assertSame('transaction ' . PHP_INT_MAX, $router->dispatch(new Request('GET', '/transactions/' . PHP_INT_MAX))->body);

        $this->expectException(NotFoundException::class);
        $router->dispatch(new Request('GET', '/transactions/9223372036854775808'));
    }

    public function testFixedPathWinsOverAPlaceholderRegisteredEarlier(): void
    {
        $router = new Router();
        $router->add(['GET'], '/admin/products/{id}', static fn (Request $request, string $id): Response => new Response(200, "product {$id}"));
        $router->add(['GET'], '/admin/products/create', static fn (Request $request): Response => new Response(200, 'create form'));

        self::assertSame('create form', $router->dispatch(new Request('GET', '/admin/products/create'))->body);
    }

    public function testWrongMethodListsTheAllowedOnes(): void
    {
        $router = new Router();
        $router->add(['GET'], '/products', static fn (Request $request): Response => new Response(200));

        try {
            $router->dispatch(new Request('DELETE', '/products'));
            self::fail('Expected 405');
        } catch (MethodNotAllowedException $e) {
            self::assertSame(['GET'], $e->allowed);
        }
    }
}
