<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/*
 * The admin's transaction filters: customer name or email, product name, and a date
 * range (whole days, both ends included). Empty filters match everything.
 */
final class TransactionFilter
{
    public function __construct(
        public readonly string $customer = '',
        public readonly string $product = '',
        public readonly ?DateTimeImmutable $from = null,
        public readonly ?DateTimeImmutable $to = null,
    ) {
    }

    /**
     * The filters from query parameters customer, product, from and to (Y-m-d). Invalid dates
     * are ignored, and a reversed range is swapped.
     * @param array<string, string> $query
     * @return self
     */
    public static function fromQuery(array $query): self
    {
        $from = self::date($query['from'] ?? '');
        $to = self::date($query['to'] ?? '');
        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return new self(
            mb_substr(trim($query['customer'] ?? ''), 0, 100),
            mb_substr(trim($query['product'] ?? ''), 0, 100),
            $from,
            $to,
        );
    }

    /**
     * The filters as query parameters, leaving out empty ones.
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return array_filter([
            'customer' => $this->customer,
            'product' => $this->product,
            'from' => $this->from?->format('Y-m-d') ?? '',
            'to' => $this->to?->format('Y-m-d') ?? '',
        ], static fn (string $value): bool => $value !== '');
    }

    /**
     * Whether any filter is set.
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->toQuery() !== [];
    }

    /**
     * Midnight of a valid Y-m-d date, or null.
     * @return DateTimeImmutable|null
     */
    private static function date(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));

        return $date !== false && $date->format('Y-m-d') === trim($value) ? $date : null;
    }
}
