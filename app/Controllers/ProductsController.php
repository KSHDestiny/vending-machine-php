<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Routing\Route;
use App\Http\Session\Session;
use App\Auth\SessionAuth;
use App\Http\View;
use App\Models\Product;
use App\Services\ProductService;
use App\Services\PurchaseService;

/*
 * Product pages: the public catalogue and details, purchasing for customers, and
 * product management for admins. Business rules live in ProductService and
 * PurchaseService; this controller only handles HTTP and views.
 */
final class ProductsController
{
    public function __construct(
        private readonly ProductService $products,
        private readonly PurchaseService $purchases,
        private readonly SessionAuth $auth,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    /**
     * The public catalogue: search, sort by name, price or stock, and pages.
     * @return Response
     */
    #[Route('/', ['GET'], ['auth'])]
    #[Route('/products', ['GET'], ['auth'])]
    public function index(Request $request): Response
    {
        return $this->view->render('products/index', ['title' => 'Products'] + $this->listing($request));
    }

    /**
     * A product's details with the purchase form.
     * @return Response
     */
    #[Route('/products/{id}', ['GET'], ['auth'])]
    public function show(Request $request, int $id): Response
    {
        return $this->details($this->products->find($id), '1', []);
    }

    /**
     * Buys a quantity, then shows the confirmation; the price comes from the database.
     * @return Response
     */
    #[Route('/products/{id}/purchase', ['POST'], ['user'])]
    public function purchase(Request $request, int $id): Response
    {
        $quantity = $request->input('quantity');
        try {
            $transaction = $this->purchases->purchase($this->auth->user(), $id, $quantity);
        } catch (ValidationException $e) {
            return $this->details($this->products->find($id), $quantity, $e->errors, 422);
        }

        $this->session->flash('Thank you! Your purchase is complete.');

        return Response::redirect("/transactions/{$transaction->id}");
    }

    /**
     * The admin product list with edit and delete actions.
     * @return Response
     */
    #[Route('/admin/products', ['GET'], ['admin'])]
    public function manage(Request $request): Response
    {
        return $this->view->render('admin/products/index', ['title' => 'Manage products'] + $this->listing($request));
    }

    /**
     * The add-product form.
     * @return Response
     */
    #[Route('/admin/products/create', ['GET'], ['admin'])]
    public function create(Request $request): Response
    {
        return $this->form(null, ['name' => '', 'price' => '', 'quantity_available' => ''], []);
    }

    /**
     * Adds the product, or shows the form again with the errors beside the fields.
     * @return Response
     */
    #[Route('/admin/products', ['POST'], ['admin'])]
    public function store(Request $request): Response
    {
        $input = $request->only('name', 'price', 'quantity_available');
        try {
            $product = $this->products->create($input);
        } catch (ValidationException $e) {
            return $this->form(null, $input, $e->errors, 422);
        }

        $this->session->flash("Added {$product->name}.");

        return Response::redirect('/admin/products');
    }

    /**
     * The edit form, filled with the product.
     * @return Response
     */
    #[Route('/admin/products/{id}/edit', ['GET'], ['admin'])]
    public function edit(Request $request, int $id): Response
    {
        $product = $this->products->find($id);
        $input = ['name' => $product->name, 'price' => $product->price, 'quantity_available' => (string) $product->quantityAvailable];

        return $this->form($product, $input, []);
    }

    /**
     * Saves the product, or shows the form again with the errors.
     * @return Response
     */
    #[Route('/admin/products/{id}', ['PUT'], ['admin'])]
    public function update(Request $request, int $id): Response
    {
        $product = $this->products->find($id);
        $input = $request->only('name', 'price', 'quantity_available');
        try {
            $product = $this->products->update($id, $input);
        } catch (ValidationException $e) {
            return $this->form($product, $input, $e->errors, 422);
        }

        $this->session->flash("Saved {$product->name}.");

        return Response::redirect('/admin/products');
    }

    /**
     * Soft-deletes the product; its purchase history stays.
     * @return Response
     */
    #[Route('/admin/products/{id}', ['DELETE'], ['admin'])]
    public function destroy(Request $request, int $id): Response
    {
        $product = $this->products->delete($id);
        $this->session->flash("Deleted {$product->name}.");

        return Response::redirect('/admin/products');
    }

    /**
     * The page of products and the filters for the ?q=&sort=&direction=&page= query.
     * @return array{products: \App\Support\Page<Product>, filters: array{q: string, sort: string, direction: string}}
     */
    private function listing(Request $request): array
    {
        $page = $this->products->list((int) $request->query('page'), $request->query('sort'), $request->query('direction'), $request->query('q'));
        $sort = in_array($request->query('sort'), ['name', 'price', 'quantity_available'], true) ? $request->query('sort') : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        return ['products' => $page, 'filters' => ['q' => trim($request->query('q')), 'sort' => $sort, 'direction' => $direction]];
    }

    /**
     * The details page with the purchase form and any errors.
     * @param array<string, string> $errors
     * @return Response
     */
    private function details(Product $product, string $quantity, array $errors, int $status = 200): Response
    {
        return $this->view->render('products/show', ['title' => $product->name, 'product' => $product, 'quantity' => $quantity, 'errors' => $errors], $status);
    }

    /**
     * The add or edit form with the submitted values and any errors.
     * @param array<string, string> $input
     * @param array<string, string> $errors
     * @return Response
     */
    private function form(?Product $product, array $input, array $errors, int $status = 200): Response
    {
        $title = $product === null ? 'Add product' : "Edit {$product->name}";

        return $this->view->render('admin/products/form', ['title' => $title, 'product' => $product, 'input' => $input, 'errors' => $errors], $status);
    }
}
