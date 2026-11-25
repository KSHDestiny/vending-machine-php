<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\SessionAuth;
use App\Http\Session\Session;
use RuntimeException;
use Throwable;

/*
 * Renders resources/views/<template>.php inside layouts/app.php. Every template
 * gets $e (HTML escaping), $money, $csrf (hidden token field), $partial,
 * $currentUser and $currentPath; escape all output with $e.
 */
final class View
{
    /** @var string The request path, for marking the current page in the navigation. */
    private string $currentPath = '/';

    public function __construct(
        private readonly string $directory,
        private readonly Session $session,
        private readonly SessionAuth $auth,
    ) {
    }

    /**
     * An HTML response with the template rendered inside the layout.
     * @param array<string, mixed> $data
     * @return Response
     */
    public function render(string $template, array $data = [], int $status = 200): Response
    {
        $content = $this->capture($template, $data);
        $page = $this->capture('layouts/app', [
            'content' => $content,
            'title' => $data['title'] ?? 'Vending Machine',
            'flash' => $this->session->takeFlash(),
        ]);

        return Response::html($page, $status);
    }

    /**
     * Sets the path of the request being handled; the Kernel calls this first.
     * @return void
     */
    public function setCurrentPath(string $path): void
    {
        $this->currentPath = $path;
    }

    /**
     * Money with at least 2 decimals and up to 3: 3.990 → 3.99, 6.885 → 6.885, 0.5 → 0.50.
     * @return string
     */
    public static function money(string $amount): string
    {
        [$whole, $fraction] = explode('.', $amount) + [1 => ''];

        return number_format((float) $whole) . '.' . str_pad(rtrim($fraction, '0'), 2, '0');
    }

    /**
     * The template's output, rendered with the data and the shared helpers.
     * @param array<string, mixed> $data
     * @return string
     */
    private function capture(string $template, array $data): string
    {
        $file = "{$this->directory}/{$template}.php";
        if (!is_file($file)) {
            throw new RuntimeException("View {$template} not found");
        }

        $e = static fn (string|int|float|null $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $data += [
            'e' => $e,
            'money' => self::money(...),
            'csrf' => '<input type="hidden" name="_token" value="' . $e($this->session->csrfToken()) . '">',
            'partial' => fn (string $name, array $values = []): string => $this->capture("partials/{$name}", $values),
            'currentUser' => $this->auth->user(),
            'currentPath' => $this->currentPath,
        ];

        return (static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
            } catch (Throwable $error) {
                ob_end_clean();
                throw $error;
            }

            return (string) ob_get_clean();
        })($file, $data);
    }
}
