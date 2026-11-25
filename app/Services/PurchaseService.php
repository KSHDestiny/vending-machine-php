<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\TransactionManager;
use App\Exceptions\AuthenticationException;
use App\Exceptions\NotFoundException;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Support\Page;
use App\Support\Validator;

/*
 * Buying a product. The stock check and the stock update happen on a row locked
 * with SELECT ... FOR UPDATE inside one transaction, so two buyers can never both
 * take the last item. The sale and its inventory movement are recorded in that transaction too. The price is always read from the database, never the request.
 */
final class PurchaseService
{
    /** @var int Transactions per page in history lists. */
    public const PER_PAGE = 10;

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly TransactionRepositoryInterface $transactions,
        private readonly InventoryService $inventory,
        private readonly TransactionManager $database,
    ) {
    }

    /**
     * The recorded purchase; throws AuthenticationException, ValidationException,
     * InsufficientStockException or NotFoundException, and then nothing is bought.
     * @return Transaction
     */
    public function purchase(?User $buyer, int $productId, string $quantity): Transaction
    {
        if ($buyer === null) {
            throw new AuthenticationException();
        }

        $quantity = trim($quantity);
        Validator::check(['quantity' => $quantity], ['quantity' => ['label' => 'Quantity', 'rules' => ['required', 'quantity']]]);

        $quantity = (int) $quantity;

        return $this->database->transaction(function () use ($buyer, $productId, $quantity): Transaction {
            // The row stays locked until commit, so no other purchase can take this stock meanwhile.
            $product = $this->products->findForUpdate($productId) ?? throw new NotFoundException('Product not found.');
            $product->ensureCanSupply($quantity);
            $total = $product->totalFor($quantity);

            $this->products->decrementStock($product->id, $quantity);
            $transaction = $this->transactions->create($buyer->id, $product->id, $quantity, $product->price, $total);
            $this->inventory->recordPurchase($buyer, $product, $quantity, $transaction->id);

            return $transaction;
        });
    }

    /**
     * One page of the user's own purchases, newest first.
     * @return Page<Transaction>
     */
    public function historyFor(User $user, int $page): Page
    {
        return $this->transactions->paginateForUser($user->id, max(1, $page), self::PER_PAGE);
    }

    /**
     * One page of every purchase, newest first (admin view).
     * @return Page<Transaction>
     */
    public function all(int $page): Page
    {
        return $this->transactions->paginate(max(1, $page), self::PER_PAGE);
    }

    /**
     * The purchase if it belongs to the user or the user is an admin; otherwise NotFoundException, so others' ids are not revealed.
     * @return Transaction
     */
    public function findFor(User $user, int $id): Transaction
    {
        $transaction = $this->transactions->find($id);
        if ($transaction === null || !$transaction->isVisibleTo($user)) {
            throw new NotFoundException('Transaction not found.');
        }

        return $transaction;
    }
}
