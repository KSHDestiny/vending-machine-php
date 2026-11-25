<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\TransactionManager;
use App\Enums\MovementType;
use App\Exceptions\NotFoundException;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Support\Page;
use App\Support\Validator;

/*
 * The only code that changes stock. Every change locks the product row, sets the new
 * stock and records a movement (type, before, after, reason, who) in the same
 * transaction, so the history always explains the current stock.
 */
final class InventoryService
{
    /** @var int Movements per page. */
    public const PER_PAGE = 15;

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly InventoryRepositoryInterface $movements,
        private readonly TransactionManager $database,
    ) {
    }

    /**
     * Adds stock: {"quantity": positive whole number, "reason": optional}; ValidationException otherwise.
     * @param array<string, string> $input quantity, reason
     * @return Product
     */
    public function restock(User $actor, int $productId, array $input): Product
    {
        $input = ['quantity' => trim($input['quantity'] ?? ''), 'reason' => trim($input['reason'] ?? '')];
        Validator::check($input, [
            'quantity' => ['label' => 'Quantity', 'rules' => ['required', 'quantity']],
            'reason' => ['label' => 'Reason', 'rules' => ['max:255']],
        ]);

        return $this->database->transaction(function () use ($actor, $productId, $input): Product {
            $product = $this->locked($productId);
            $this->setStock($product, $product->quantityAvailable + (int) $input['quantity'], MovementType::Restock, $input['reason'] === '' ? null : $input['reason'], $actor);

            return $this->products->find($productId) ?? throw new NotFoundException('Product not found.');
        });
    }

    /**
     * Sets the stock to $quantity as an ADJUSTMENT (e.g. from the product form) inside the caller's
     * transaction; records nothing when the stock is unchanged.
     * @return void
     */
    public function adjustWithinTransaction(User $actor, int $productId, int $quantity, string $reason): void
    {
        $product = $this->locked($productId);
        if ($product->quantityAvailable !== $quantity) {
            $this->setStock($product, $quantity, MovementType::Adjustment, $reason, $actor);
        }
    }

    /**
     * Records a new product's starting stock, inside the caller's transaction.
     * @return void
     */
    public function recordInitialStock(User $actor, Product $product): void
    {
        $this->movements->record($product->id, MovementType::InitialStock, $product->quantityAvailable, 0, null, $actor->id);
    }

    /**
     * Records a sale, inside the purchase transaction after the stock was taken from the locked row.
     * @return void
     */
    public function recordPurchase(User $buyer, Product $before, int $quantity, int $transactionId): void
    {
        $this->movements->record($before->id, MovementType::Purchase, -$quantity, $before->quantityAvailable, null, $buyer->id, $transactionId);
    }

    /**
     * One page of the product's movements, newest first; unknown type filters are ignored.
     * @return Page<InventoryMovement>
     */
    public function history(int $productId, int $page, string $type): Page
    {
        return $this->movements->paginateForProduct($productId, max(1, $page), self::PER_PAGE, MovementType::tryFrom($type));
    }

    /**
     * The product with its row locked until the transaction ends.
     * @return Product
     */
    private function locked(int $productId): Product
    {
        return $this->products->findForUpdate($productId) ?? throw new NotFoundException('Product not found.');
    }

    /**
     * Sets the stock on the locked product and records why.
     * @return void
     */
    private function setStock(Product $product, int $quantity, MovementType $type, ?string $reason, User $actor): void
    {
        $this->products->setStock($product->id, $quantity);
        $this->movements->record($product->id, $type, $quantity - $product->quantityAvailable, $product->quantityAvailable, $reason, $actor->id);
    }
}
