<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /** @var list<string> Methods an HTML form may ask for with a hidden _method field. */
    private const OVERRIDABLE = ['PUT', 'PATCH', 'DELETE'];

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        /** @var array<string, mixed> Form fields or a decoded JSON body. */
        private readonly array $body = [],
        /** @var array<string, mixed> URL query parameters. */
        private readonly array $query = [],
        /** @var array<string, string> Request headers by name. */
        private readonly array $headers = [],
    ) {
    }

    /**
     * The current request from PHP's globals, with JSON bodies decoded and _method applied to form posts.
     * @return self
     */
    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $body = $_POST;
        if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $json = json_decode((string) file_get_contents('php://input'), true);
            $body = is_array($json) ? $json : [];
        }

        $override = strtoupper(is_string($body['_method'] ?? null) ? $body['_method'] : '');
        if ($method === 'POST' && in_array($override, self::OVERRIDABLE, true)) {
            $method = $override;
        }

        // Apache hides Authorization from $_SERVER; getallheaders() includes it.
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        return new self($method, is_string($path) ? $path : '/', $body, $_GET, $headers);
    }

    /**
     * A body field as a string; '' when missing. JSON numbers become strings ("2").
     * @return string
     */
    public function input(string $key): string
    {
        return self::string($this->body[$key] ?? null);
    }

    /**
     * The named body fields as strings, e.g. to validate or refill a form.
     * @return array<string, string>
     */
    public function only(string ...$keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->input($key);
        }

        return $values;
    }

    /**
     * A query parameter as a string; '' when missing.
     * @return string
     */
    public function query(string $key): string
    {
        return self::string($this->query[$key] ?? null);
    }

    /**
     * A header by case-insensitive name; '' when missing.
     * @return string
     */
    public function header(string $name): string
    {
        return self::string(array_change_key_case($this->headers, CASE_LOWER)[strtolower($name)] ?? null);
    }

    /**
     * The token from "Authorization: Bearer <token>", or ''.
     * @return string
     */
    public function bearerToken(): string
    {
        return preg_match('/^Bearer\s+(\S+)$/i', $this->header('Authorization'), $match) === 1 ? $match[1] : '';
    }

    /**
     * Whether this is an API request, answered with JSON.
     * @return bool
     */
    public function isApi(): bool
    {
        return $this->path === '/api' || str_starts_with($this->path, '/api/');
    }

    /**
     * The value as a string if it's a string or number, else ''.
     * @return string
     */
    private static function string(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
    }
}
