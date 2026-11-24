<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Exceptions\MethodNotAllowedException;
use App\Exceptions\NotFoundException;
use App\Http\Request;
use App\Http\Response;
use Closure;
use LogicException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionObject;

/*
 * Matches a request to a route, runs the route's middleware, then its handler.
 *
 * Fixed paths are matched before placeholder paths, so /admin/products/create is
 * never caught by /admin/products/{id}, whatever order routes are registered in.
 */
final class Router
{
    /** @var list<array{methods: list<string>, pattern: string, handler: Closure, ints: list<string>, middleware: list<Middleware>}> Routes in match order, fixed paths first. */
    private array $routes = [];

    /** @var int How many fixed-path routes are at the front of $routes. */
    private int $literalCount = 0;

    /** @var array<string, Middleware> Middleware by alias, e.g. 'admin'. */
    private array $aliases = [];

    /** @var list<Middleware> Middleware of the group() calls currently running. */
    private array $groupMiddleware = [];

    /**
     * Names a middleware so routes and groups can refer to it.
     * @return void
     */
    public function alias(string $name, Middleware $middleware): void
    {
        $this->aliases[$name] = $middleware;
    }

    /**
     * Registers routes inside $routes with the group's middleware running first.
     * @param list<string> $middleware
     * @param Closure(self): void $routes
     * @return void
     */
    public function group(array $middleware, Closure $routes): void
    {
        $outer = $this->groupMiddleware;
        $this->groupMiddleware = [...$outer, ...$this->resolve($middleware)];
        try {
            $routes($this);
        } finally {
            $this->groupMiddleware = $outer;
        }
    }

    /**
     * Registers every public method of the controller that has #[Route] attributes.
     * @return void
     */
    public function registerController(object $controller): void
    {
        foreach ((new ReflectionObject($controller))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $ints = [];
            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof ReflectionNamedType && $type->getName() === 'int') {
                    $ints[] = $parameter->getName();
                }
            }

            foreach ($method->getAttributes(Route::class) as $attribute) {
                $route = $attribute->newInstance();
                $this->add($route->methods, $route->path, $method->getClosure($controller), $route->middleware, $ints);
            }
        }
    }

    /**
     * Adds a route; each {placeholder} is passed to the handler as a named argument.
     * @param list<string> $methods
     * @param list<string> $middleware
     * @param list<string> $ints placeholders that must be digits and are passed as int
     * @return void
     */
    public function add(array $methods, string $path, Closure $handler, array $middleware = [], array $ints = []): void
    {
        $route = [
            'methods' => array_map(strtoupper(...), $methods),
            'pattern' => self::compile($path, $ints),
            'handler' => $handler,
            'ints' => $ints,
            'middleware' => [...$this->groupMiddleware, ...$this->resolve($middleware)],
        ];

        if (str_contains($path, '{')) {
            $this->routes[] = $route;
        } else {
            array_splice($this->routes, $this->literalCount++, 0, [$route]);
        }
    }

    /**
     * The matching route's response; throws NotFoundException or MethodNotAllowedException.
     * @return Response
     */
    public function dispatch(Request $request): Response
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $request->path, $matches) !== 1) {
                continue;
            }
            if (!in_array($request->method, $route['methods'], true)) {
                array_push($allowed, ...$route['methods']);
                continue;
            }

            $arguments = [];
            foreach ($matches as $name => $value) {
                if (is_string($name)) {
                    $arguments[$name] = in_array($name, $route['ints'], true) ? filter_var($value, FILTER_VALIDATE_INT) : $value;
                }
            }
            // An id too big for a PHP int can't exist, so it's not found rather than a wrong id.
            if (in_array(false, $arguments, true)) {
                continue;
            }

            // Wrap the handler in its middleware so the first one runs first.
            $next = static fn (): Response => ($route['handler'])($request, ...$arguments);
            foreach (array_reverse($route['middleware']) as $middleware) {
                $next = static fn (): Response => $middleware->handle($request, $next);
            }

            return $next();
        }

        if ($allowed !== []) {
            throw new MethodNotAllowedException(array_values(array_unique($allowed)));
        }

        throw new NotFoundException('Page not found.');
    }

    /**
     * The middleware for these aliases; throws on an unknown alias.
     * @param list<string> $names
     * @return list<Middleware>
     */
    private function resolve(array $names): array
    {
        return array_map(
            fn (string $name): Middleware => $this->aliases[$name] ?? throw new LogicException("Unknown middleware alias '{$name}'"),
            $names,
        );
    }

    /**
     * The path as a regex with a named group per placeholder; int placeholders match 1-19 digits.
     * @param list<string> $ints
     * @return string
     */
    private static function compile(string $path, array $ints): string
    {
        $parts = preg_split('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $path, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$path];
        $regex = '';
        foreach ($parts as $index => $part) {
            $regex .= $index % 2 === 0 ? preg_quote($part, '#') : "(?P<{$part}>" . (in_array($part, $ints, true) ? '\d{1,19}' : '[^/]+') . ')';
        }

        return "#^{$regex}$#";
    }
}
