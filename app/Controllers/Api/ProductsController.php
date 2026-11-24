<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Exceptions\AuthenticationException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Routing\Route;
use App\Auth\TokenAuth;
use App\Models\Product;
use App\Services\ProductService;
use App\Services\PurchaseService;

/*
 * Products as JSON for other frontends. Reading is public; managing needs an admin
 * token and buying a customer token. Uses the same services as the web pages.
 */
final class ProductsController
{
    public function __construct(
        private readonly ProductService $products,
        private readonly PurchaseService $purchases,
        private readonly TokenAuth $auth,
    ) {
    }

    /**
     * One page of products: ?q=&sort=name|price|quantity_available&direction=asc|desc&page=.
     * @return Response
     */
    #[Route('/api/products', ['GET'], ['jwt'])]
    public function index(Request $request): Response
    {
        $page = $this->products->list((int) $request->query('page'), $request->query('sort'), $request->query('direction'), $request->query('q'));

        return Response::json(['data' => array_map(static fn (Product $p): array => $p->toArray(), $page->items), 'meta' => $page->meta()]);
    }

    /**
     * One product.
     * @return Response
     */
    #[Route('/api/products/{id}', ['GET'], ['jwt'])]
    public function show(Request $request, int $id): Response
    {
        return Response::json(['data' => $this->products->find($id)->toArray()]);
    }

    /**
     * Creates a product: 201 with a Location header.
     * @return Response
     */
    #[Route('/api/products', ['POST'], ['jwt.admin'])]
    public function store(Request $request): Response
    {
        $product = $this->products->create($request->only('name', 'price', 'quantity_available'));

        return Response::json(['data' => $product->toArray()], 201, ['Location' => "/api/products/{$product->id}"]);
    }

    /**
     * Replaces a product's name, price and stock.
     * @return Response
     */
    #[Route('/api/products/{id}', ['PUT'], ['jwt.admin'])]
    public function update(Request $request, int $id): Response
    {
        return Response::json(['data' => $this->products->update($id, $request->only('name', 'price', 'quantity_available'))->toArray()]);
    }

    /**
     * Soft-deletes a product: 204.
     * @return Response
     */
    #[Route('/api/products/{id}', ['DELETE'], ['jwt.admin'])]
    public function destroy(Request $request, int $id): Response
    {
        $this->products->delete($id);

        return Response::noContent();
    }

    /**
     * Buys {"quantity": n} for the token's user: 201 with the transaction.
     * @return Response
     */
    #[Route('/api/products/{id}/purchase', ['POST'], ['jwt.user'])]
    public function purchase(Request $request, int $id): Response
    {
        $buyer = $this->auth->user($request) ?? throw new AuthenticationException();
        $transaction = $this->purchases->purchase($buyer, $id, $request->input('quantity'));

        return Response::json(['data' => $transaction->toArray()], 201);
    }
}
