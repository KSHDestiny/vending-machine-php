<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\TransactionManager;
use App\Enums\ProductStatus;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Support\Page;
use App\Support\Validator;

/*
 * Product rules: listing (customers see only products on sale), and admin
 * create/update/delete with server-side validation (name required, price above 0,
 * stock 0 or more). Stock changes are recorded through InventoryService.
 */
final class ProductService
{
    /** @var int Products per page in lists. */
    public const PER_PAGE = 10;

    /** @var array<string, array{label: string, rules: list<string>}> The product form's fields and rules. */
    public const RULES = [
        'name' => ['label' => 'Name', 'rules' => ['required', 'max:100']],
        'price' => ['label' => 'Price', 'rules' => ['required', 'price']],
        'quantity_available' => ['label' => 'Quantity available', 'rules' => ['required', 'stock']],
    ];

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly InventoryService $inventory,
        private readonly TransactionManager $database,
    ) {
    }

    /**
     * One page of products; an unknown sort or direction falls back to name, ascending.
     * @return Page<Product>
     */
    public function list(int $page, string $sort = 'name', string $direction = 'asc', string $search = '', bool $onSaleOnly = false): Page
    {
        $sort = in_array($sort, ['name', 'price', 'quantity_available'], true) ? $sort : 'name';
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return $this->products->paginate(max(1, $page), self::PER_PAGE, $sort, $direction, mb_substr(trim($search), 0, 100), $onSaleOnly);
    }

    /**
     * The product; throws NotFoundException if it doesn't exist or was deleted.
     * @return Product
     */
    public function find(int $id): Product
    {
        return $this->products->find($id) ?? throw new NotFoundException('Product not found.');
    }

    /**
     * The product if customers can see it; NotFoundException for products off sale, so they stay hidden.
     * @return Product
     */
    public function findOnSale(int $id): Product
    {
        $product = $this->find($id);

        return $product->isOnSale() ? $product : throw new NotFoundException('Product not found.');
    }

    /**
     * The new product, with its starting stock recorded; throws ValidationException for invalid input.
     * @param array<string, string> $input name, price, quantity_available
     * @return Product
     */
    public function create(User $actor, array $input): Product
    {
        $input = $this->validated($input);

        return $this->database->transaction(function () use ($actor, $input): Product {
            $product = $this->products->create($input['name'], $input['price'], (int) $input['quantity_available']);
            $this->inventory->recordInitialStock($actor, $product);

            return $product;
        });
    }

    /**
     * The updated product; a changed stock is recorded as an adjustment. Status is 'active' or
     * 'inactive' (empty keeps the current one). Throws NotFoundException or ValidationException.
     * @param array<string, string> $input name, price, quantity_available, status
     * @return Product
     */
    public function update(User $actor, int $id, array $input): Product
    {
        $current = $this->find($id);
        $status = trim($input['status'] ?? '');
        $status = $status === '' ? $current->status : (ProductStatus::tryFrom($status) ?? throw new ValidationException(['status' => 'Choose on sale or off sale.']));
        $input = $this->validated($input);

        $this->database->transaction(function () use ($actor, $id, $input, $status): void {
            $this->products->update($id, $input['name'], $input['price'], $status);
            $this->inventory->adjustWithinTransaction($actor, $id, (int) $input['quantity_available'], 'Changed on the product form');
        });

        return $this->find($id);
    }

    /**
     * Soft-deletes the product and returns it; its transactions keep it.
     * @return Product
     */
    public function delete(int $id): Product
    {
        $product = $this->find($id);
        $this->products->delete($id);

        return $product;
    }

    /**
     * The input with every value trimmed, after validation.
     * @param array<string, string> $input
     * @return array{name: string, price: string, quantity_available: string}
     */
    private function validated(array $input): array
    {
        $input = [
            'name' => trim($input['name'] ?? ''),
            'price' => trim($input['price'] ?? ''),
            'quantity_available' => trim($input['quantity_available'] ?? ''),
        ];
        Validator::check($input, self::RULES);

        return $input;
    }
}
