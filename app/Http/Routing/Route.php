<?php

declare(strict_types=1);

namespace App\Http\Routing;

use Attribute;

/*
 * Attribute routing: put #[Route('/products/{id}/purchase', ['POST'], ['user'])] on a
 * controller method and Router::registerController() registers it. A {placeholder}
 * whose method parameter is an int only matches digits.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Route
{
    public function __construct(
        public readonly string $path,
        /** @var list<string> HTTP methods, e.g. ['GET']. */
        public readonly array $methods = ['GET'],
        /** @var list<string> Middleware aliases from routes/*.php, run in order, e.g. ['admin']. */
        public readonly array $middleware = [],
    ) {
    }
}
