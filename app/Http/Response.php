<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    public function __construct(
        public readonly int $status,
        public readonly string $body = '',
        /** @var array<string, string> Response headers by name. */
        public readonly array $headers = [],
    ) {
    }

    /**
     * A JSON response.
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @return self
     */
    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        return new self($status, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), ['Content-Type' => 'application/json; charset=utf-8'] + $headers);
    }

    /**
     * An HTML response.
     * @return self
     */
    public static function html(string $body, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * A redirect; 303 makes the browser follow with GET, so refreshing never re-submits a form.
     * @return self
     */
    public static function redirect(string $location, int $status = 303): self
    {
        return new self($status, '', ['Location' => $location]);
    }

    /**
     * An empty response, e.g. 204 after a delete.
     * @return self
     */
    public static function noContent(): self
    {
        return new self(204);
    }

    /**
     * Sends the status, headers and body.
     * @return void
     */
    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
    }
}
