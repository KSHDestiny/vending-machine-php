<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Tests\Support\Application;

/*
 * Every response carries a Content-Security-Policy: app pages allow only their own
 * scripts, /docs also allows the pinned Swagger UI files and its one inline script by
 * hash. HSTS is sent only in production, where the site is HTTPS-only.
 */
final class SecurityHeadersTest extends TestCase
{
    public function testAppPagesAllowOnlyTheirOwnScriptsAndCannotBeFramed(): void
    {
        $headers = (new Application())->send('GET', '/login')->headers;
        $csp = self::directives($headers['Content-Security-Policy'] ?? '');

        self::assertSame("'self'", $csp['default-src']);
        self::assertSame("'self'", $csp['script-src'], 'no inline scripts, no other origins');
        self::assertSame("'none'", $csp['frame-ancestors']);
        self::assertSame("'none'", $csp['object-src']);
        self::assertSame("'self'", $csp['form-action']);
        self::assertSame("'self'", $csp['base-uri']);
        self::assertSame('camera=(), microphone=(), geolocation=(), payment=()', $headers['Permissions-Policy'] ?? null);
        self::assertArrayNotHasKey('Strict-Transport-Security', $headers, 'not over plain HTTP in development');
    }

    public function testErrorResponsesAndApiResponsesGetTheHeadersToo(): void
    {
        $app = new Application();

        self::assertArrayHasKey('Content-Security-Policy', $app->send('GET', '/no-such-page')->headers);
        self::assertArrayHasKey('Content-Security-Policy', $app->api('GET', '/api/products')->headers);
    }

    public function testTheDocsPageAllowsOnlyThePinnedCdnAndItsOwnInlineScript(): void
    {
        $response = (new Application())->send('GET', '/docs');
        $csp = self::directives($response->headers['Content-Security-Policy'] ?? '');
        preg_match('#<script>(.*?)</script>#s', $response->body, $inline);

        self::assertSame("'self' https://cdn.jsdelivr.net 'sha256-" . base64_encode(hash('sha256', $inline[1] ?? '', true)) . "'", $csp['script-src'], 'the inline script is allowed by its exact hash');
        self::assertStringContainsString('https://cdn.jsdelivr.net', $csp['style-src']);
        self::assertSame("'self'", $csp['connect-src'], 'Try it out only calls this API');
        self::assertSame("'none'", $csp['frame-ancestors']);
    }

    public function testProductionAddsHsts(): void
    {
        $headers = (new Application(production: true))->send('GET', '/login')->headers;

        self::assertSame('max-age=31536000; includeSubDomains', $headers['Strict-Transport-Security'] ?? null);
    }

    /**
     * The policy as directive => value.
     * @return array<string, string>
     */
    private static function directives(string $policy): array
    {
        $directives = [];
        foreach (array_filter(array_map('trim', explode(';', $policy))) as $directive) {
            [$name, $value] = explode(' ', $directive, 2) + [1 => ''];
            $directives[$name] = $value;
        }

        return $directives;
    }
}
