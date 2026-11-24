<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One page of a longer list, with what the pagination links need.
 *
 * @template T
 */
final class Page
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    /**
     * The number of the last page; 1 when the list is empty.
     * @return int
     */
    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    /**
     * The same page with each item converted, e.g. models to JSON arrays.
     * @template U
     * @param callable(T): U $map
     * @return Page<U>
     */
    public function map(callable $map): self
    {
        return new self(array_map($map, $this->items), $this->total, $this->page, $this->perPage);
    }

    /**
     * The pagination details for an API response.
     * @return array{page: int, per_page: int, total: int, last_page: int}
     */
    public function meta(): array
    {
        return ['page' => $this->page, 'per_page' => $this->perPage, 'total' => $this->total, 'last_page' => $this->lastPage()];
    }
}
