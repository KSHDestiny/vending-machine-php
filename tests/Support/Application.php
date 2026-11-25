<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Http\Kernel;
use App\Http\Request;
use App\Http\Response;
use App\Models\User;
use App\Services\JwtService;

/*
 * Sends requests through the real bootstrap, routes, middleware and controllers.
 * Each request gets a fresh kernel (as each PHP request does), while the session
 * persists between them like a browser cookie.
 */
final class Application
{
    /** @var string Signing secret used by every test. */
    public const JWT_SECRET = 'test-secret-that-is-at-least-32-bytes-long';

    /** @var ArraySessionStore The session shared by the requests. */
    public ArraySessionStore $session;

    /**
     * @param array<string, object> $overrides repositories or the transaction manager to replace
     * @param array{host: string, port: int, name: string, user: string, password: string}|null $database
     * @param bool $production builds the app as in production (Secure cookies, HSTS)
     */
    public function __construct(private readonly array $overrides = [], private readonly ?array $database = null, private readonly bool $production = false)
    {
        $this->session = new ArraySessionStore();
    }

    /**
     * The response to a request, built like the browser or API client would send it.
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @return Response
     */
    public function send(string $method, string $path, array $body = [], array $query = [], array $headers = []): Response
    {
        return $this->kernel()->handle(new Request($method, $path, $body, $query, $headers));
    }

    /**
     * The page a browser lands on after a redirect (GET of its Location), or the response itself.
     * @return Response
     */
    public function follow(Response $response): Response
    {
        $location = $response->headers['Location'] ?? null;

        return in_array($response->status, [301, 302, 303], true) && is_string($location)
            ? $this->send('GET', (string) parse_url($location, PHP_URL_PATH))
            : $response;
    }

    /**
     * The response to a browser form post, with the session's CSRF token added.
     * @param array<string, mixed> $body
     * @return Response
     */
    public function submit(string $method, string $path, array $body = []): Response
    {
        return $this->send($method, $path, $body + ['_token' => $this->csrfToken()]);
    }

    /**
     * The response to an API request, with a bearer token if given.
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     * @return Response
     */
    public function api(string $method, string $path, ?string $token = null, array $body = [], array $query = []): Response
    {
        return $this->send($method, $path, $body, $query, $token === null ? [] : ['Authorization' => "Bearer {$token}"]);
    }

    /**
     * Signs the user in, as a successful login would.
     * @return void
     */
    public function actingAs(User $user): void
    {
        $this->session->set('auth', ['id' => $user->id, 'role' => $user->role->value]);
    }

    /**
     * A valid API token for the user.
     * @return string
     */
    public function tokenFor(User $user): string
    {
        return (new JwtService(self::JWT_SECRET))->issue($user);
    }

    /**
     * The session's CSRF token, created on first use as the pages do.
     * @return string
     */
    public function csrfToken(): string
    {
        $token = $this->session->get('_csrf');
        if (!is_string($token)) {
            $token = bin2hex(random_bytes(32));
            $this->session->set('_csrf', $token);
        }

        return $token;
    }

    /**
     * The decoded JSON body of a response.
     * @return array<string, mixed>
     */
    public static function json(Response $response): array
    {
        $data = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);

        return is_array($data) ? $data : [];
    }

    /**
     * A fresh kernel from the real bootstrap with the test configuration.
     * @return Kernel
     */
    private function kernel(): Kernel
    {
        $root = dirname(__DIR__, 2);
        $config = [
            'production' => $this->production,
            'db' => $this->database ?? ['host' => 'unused', 'port' => 3306, 'name' => 'unused', 'user' => 'unused', 'password' => 'unused'],
            'jwt' => ['secret' => self::JWT_SECRET, 'ttl' => 3600],
            'paths' => ['views' => $root . '/resources/views', 'migrations' => $root . '/database/migrations', 'log' => sys_get_temp_dir() . '/vending-test.log'],
        ];

        return (require $root . '/bootstrap/app.php')($config, $this->session, $this->overrides);
    }
}
