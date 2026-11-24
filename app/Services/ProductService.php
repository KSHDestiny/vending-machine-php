<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Support\Page;
use App\Support\Validator;

/*
 * Product rules: listing, and admin create/update/delete with server-side
 * validation (name required, price above 0, stock 0 or more).
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

    public function __construct(private readonly ProductRepositoryInterface $products)
    {
    }

    /**
     * One page of products; an unknown sort or direction falls back to name, ascending.
     * @return Page<Product>
     */
    public function list(int $page, string $sort = 'name', string $direction = 'asc', string $search = ''): Page
    {
        $sort = in_array($sort, ['name', 'price', 'quantity_available'], true) ? $sort : 'name';
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return $this->products->paginate(max(1, $page), self::PER_PAGE, $sort, $direction, mb_substr(trim($search), 0, 100));
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
     * The new product; throws ValidationException for invalid input.
     * @param array<string, string> $input name, price, quantity_available
     * @return Product
     */
    public function create(array $input): Product
    {
        $input = $this->validated($input);

        return $this->products->create($input['name'], $input['price'], (int) $input['quantity_available']);
    }

    /**
     * The updated product; throws NotFoundException or ValidationException.
     * @param array<string, string> $input name, price, quantity_available
     * @return Product
     */
    public function update(int $id, array $input): Product
    {
        $this->find($id);
        $input = $this->validated($input);
        $this->products->update($id, $input['name'], $input['price'], (int) $input['quantity_available']);

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
