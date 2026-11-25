<?php

declare(strict_types=1);

namespace App\Http\Session;

/*
 * The web session: the signed-in user id, one-time flash messages and the CSRF token.
 */
final class Session
{
    /** @var string Key of the CSRF token. */
    private const CSRF = '_csrf';

    /** @var string Key of the one-time flash message. */
    private const FLASH = '_flash';

    /** @var string Session key for a refused form's errors and values (flashForm). */
    private const FORM = '_form';

    public function __construct(private readonly SessionStore $store)
    {
    }

    /**
     * The stored value, or null.
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $this->store->get($key);
    }

    /**
     * Stores a value.
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->store->set($key, $value);
    }

    /**
     * Stores a message that the next page shows once.
     * @return void
     */
    public function flash(string $message): void
    {
        $this->store->set(self::FLASH, $message);
    }

    /**
     * The flash message, removed so a refresh doesn't show it again, or null.
     * @return string|null
     */
    public function takeFlash(): ?string
    {
        $message = $this->store->get(self::FLASH);
        if ($message !== null) {
            $this->store->remove(self::FLASH);
        }

        return is_string($message) ? $message : null;
    }

    /**
     * Keeps a refused form's errors and submitted values for the next page (post, then redirect),
     * so reloading that page never resends the form. Never pass passwords in $input.
     * @param array<string, string> $input
     * @param array<string, string> $errors
     * @return void
     */
    public function flashForm(string $form, array $input, array $errors): void
    {
        $this->store->set(self::FORM, ['form' => $form, 'input' => $input, 'errors' => $errors]);
    }

    /**
     * The errors and values kept by flashForm() for this form, removed as they are read; empty otherwise.
     * @return array{input: array<string, string>, errors: array<string, string>}
     */
    public function takeForm(string $form): array
    {
        $kept = $this->store->get(self::FORM);
        if (!is_array($kept) || ($kept['form'] ?? null) !== $form) {
            return ['input' => [], 'errors' => []];
        }
        $this->store->remove(self::FORM);
        $strings = static fn (mixed $values): array => is_array($values) ? array_map('strval', array_filter($values, 'is_scalar')) : [];

        return ['input' => $strings($kept['input'] ?? []), 'errors' => $strings($kept['errors'] ?? [])];
    }

    /**
     * The session's CSRF token, created on first use.
     * @return string
     */
    public function csrfToken(): string
    {
        $token = $this->store->get(self::CSRF);
        if (!is_string($token)) {
            $token = bin2hex(random_bytes(32));
            $this->store->set(self::CSRF, $token);
        }

        return $token;
    }

    /**
     * Whether the token matches the session's CSRF token (constant-time).
     * @return bool
     */
    public function isValidCsrfToken(string $token): bool
    {
        $expected = $this->store->get(self::CSRF);

        return is_string($expected) && $token !== '' && hash_equals($expected, $token);
    }

    /**
     * A new session id and CSRF token, e.g. after login.
     * @return void
     */
    public function regenerate(): void
    {
        $this->store->regenerate();
        $this->store->remove(self::CSRF);
    }

    /**
     * Ends the session.
     * @return void
     */
    public function destroy(): void
    {
        $this->store->destroy();
    }
}
