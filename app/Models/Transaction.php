<?php

declare(strict_types=1);

namespace App\Models;

/*
 * One purchase, with who may see it. The product and buyer names are filled in
 * when it was loaded with them (lists and the confirmation page).
 */
final class Transaction
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly int $productId,
        public readonly int $quantity,
        /** @var string The price at purchase time, e.g. "6.885". */
        public readonly string $unitPrice,
        /** @var string unit_price × quantity, exact, e.g. "13.770". */
        public readonly string $totalPrice,
        public readonly string $createdAt,
        public readonly ?string $productName = null,
        public readonly ?string $userName = null,
        public readonly ?string $userEmail = null,
    ) {
    }

    /**
     * Whether the user may see this purchase: its buyer, or an admin.
     * @return bool
     */
    public function isVisibleTo(User $user): bool
    {
        return $user->canManageProducts() || $this->userId === $user->id;
    }

    /**
     * The transaction as API fields; fields that weren't loaded are left out.
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'user_id' => $this->userId,
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'total_price' => $this->totalPrice,
            'created_at' => $this->createdAt,
            'user_email' => $this->userEmail,
        ], static fn (int|string|null $value): bool => $value !== null);
    }
}
