<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ValidationException;
use InvalidArgumentException;

/*
 * Server-side validation. Each field lists rules checked in order and reports
 * its first failing rule:
 *
 *   required, email, max:N / min:N (characters), max_bytes:N, strong_password,
 *   same:field, price (positive, up to 3 decimals), stock (whole number 0 or more),
 *   quantity (whole number 1 or more)
 */
final class Validator
{
    /** @var string The largest amount DECIMAL(10,3) can store. */
    public const MAX_AMOUNT = '9999999.999';

    /** @var list<string> Shared bcrypt-safe policy for new passwords. */
    public const PASSWORD_RULES = ['required', 'min:8', 'max_bytes:72', 'strong_password'];

    /**
     * Throws ValidationException with one message per invalid field.
     * @param array<string, string> $data
     * @param array<string, array{label: string, rules: list<string>}> $fields
     * @return void
     */
    public static function check(array $data, array $fields): void
    {
        $errors = [];
        foreach ($fields as $name => ['label' => $label, 'rules' => $rules]) {
            foreach ($rules as $rule) {
                $error = self::failure($rule, $data[$name] ?? '', $label, $data);
                if ($error !== null) {
                    $errors[$name] = $error;
                    break;
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /**
     * The error message if the value breaks the rule, or null.
     * @param array<string, string> $data
     * @return string|null
     */
    private static function failure(string $rule, string $value, string $label, array $data): ?string
    {
        [$name, $argument] = explode(':', $rule, 2) + [1 => ''];

        return match ($name) {
            'required' => trim($value) === '' ? "{$label} is required." : null,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? "{$label} must be a valid email address." : null,
            'max' => mb_strlen($value) > (int) $argument ? "{$label} must be at most {$argument} characters." : null,
            'min' => mb_strlen($value) < (int) $argument ? "{$label} must be at least {$argument} characters." : null,
            'max_bytes' => strlen($value) > (int) $argument ? "{$label} must be at most {$argument} bytes." : null,
            'strong_password' => self::isStrongPassword($value) ? null : "{$label} must include uppercase and lowercase letters, a number, and a symbol.",
            'same' => $value !== ($data[$argument] ?? null) ? "{$label} does not match." : null,
            'price' => self::isPrice($value) ? null : "{$label} must be greater than 0, with up to 3 decimals.",
            'stock' => self::isCount($value, 0) ? null : "{$label} must be a whole number of 0 or more.",
            'quantity' => self::isCount($value, 1) ? null : "{$label} must be a whole number of 1 or more.",
            default => throw new InvalidArgumentException("Unknown validation rule: {$rule}"),
        };
    }

    private static function isStrongPassword(string $value): bool
    {
        return !str_contains($value, "\0")
            && preg_match('/[A-Z]/', $value) === 1
            && preg_match('/[a-z]/', $value) === 1
            && preg_match('/[0-9]/', $value) === 1
            && preg_match('/[^A-Za-z0-9\s]/u', $value) === 1;
    }

    /**
     * Whether the value is a positive amount with up to 3 decimals that fits DECIMAL(10,3).
     * @return bool
     */
    private static function isPrice(string $value): bool
    {
        return is_numeric($value) && preg_match('/^\d{1,7}(\.\d{1,3})?$/', $value) === 1 && bccomp($value, '0', 3) > 0;
    }

    /**
     * Whether the value is a whole number of up to nine digits and at least $min.
     * @return bool
     */
    private static function isCount(string $value, int $min): bool
    {
        return ctype_digit($value) && strlen($value) <= 9 && (int) $value >= $min;
    }
}
