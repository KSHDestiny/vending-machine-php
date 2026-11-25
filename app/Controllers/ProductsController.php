<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Routing\Route;
use App\Http\Session\Session;
use App\Auth\SessionAuth;
use App\Http\View;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
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
        private readonly InventoryService $inventory,
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
        return $this->view->render('products/index', ['title' => 'Products'] + $this->listing($request, onSaleOnly: true));
    }

    /**
     * A product's details with the purchase form.
     * @return Response
     */
    #[Route('/products/{id}', ['GET'], ['auth'])]
    public function show(Request $request, int $id): Response
    {
        // Customers only see products on sale; admins can open any product.
        $product = $this->auth->user()?->canManageProducts() ? $this->products->find($id) : $this->products->findOnSale($id);

        $kept = $this->session->takeForm("purchase-{$id}");

        return $this->details($product, $kept['input']['quantity'] ?? '1', $kept['errors']);
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
            $this->session->flashForm("purchase-{$id}", ['quantity' => $quantity], $e->errors);

            return Response::redirect("/products/{$id}");
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
        $kept = $this->session->takeForm('product-new');

        return $this->form(null, $kept['input'] + ['name' => '', 'price' => '', 'quantity_available' => ''], $kept['errors']);
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
            $product = $this->products->create($this->admin(), $input);
        } catch (ValidationException $e) {
            $this->session->flashForm('product-new', $input, $e->errors);

            return Response::redirect('/admin/products/create');
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
        $input = ['name' => $product->name, 'price' => $product->price, 'quantity_available' => (string) $product->quantityAvailable, 'status' => $product->status->value];
        $kept = $this->session->takeForm("product-{$id}");

        return $this->form($product, $kept['input'] + $input, $kept['errors']);
    }

    /**
     * Saves the product, or shows the form again with the errors.
     * @return Response
     */
    #[Route('/admin/products/{id}', ['PUT'], ['admin'])]
    public function update(Request $request, int $id): Response
    {
        $input = $request->only('name', 'price', 'quantity_available', 'status');
        try {
            $product = $this->products->update($this->admin(), $id, $input);
        } catch (ValidationException $e) {
            $this->session->flashForm("product-{$id}", $input, $e->errors);

            return Response::redirect("/admin/products/{$id}/edit");
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
     * The product's stock history (?type= filters by movement type) with the restock form.
     * @return Response
     */
    #[Route('/admin/products/{id}/inventory', ['GET'], ['admin'])]
    public function inventory(Request $request, int $id): Response
    {
        $kept = $this->session->takeForm("restock-{$id}");

        return $this->inventoryPage($request, $id, $kept['input'] + ['quantity' => '', 'reason' => ''], $kept['errors']);
    }

    /**
     * Adds stock with an optional reason, or shows the page again with the errors.
     * @return Response
     */
    #[Route('/admin/products/{id}/restock', ['POST'], ['admin'])]
    public function restock(Request $request, int $id): Response
    {
        $input = $request->only('quantity', 'reason');
        try {
            $product = $this->inventory->restock($this->admin(), $id, $input);
        } catch (ValidationException $e) {
            $this->session->flashForm("restock-{$id}", $input, $e->errors);

            return Response::redirect("/admin/products/{$id}/inventory");
        }

        $this->session->flash("Added {$input['quantity']} to {$product->name}; {$product->quantityAvailable} in stock now.");

        return Response::redirect("/admin/products/{$id}/inventory");
    }

    /**
     * The page of products and the filters for the ?q=&sort=&direction=&page= query.
     * @return array{products: \App\Support\Page<Product>, filters: array{q: string, sort: string, direction: string}}
     */
    private function listing(Request $request, bool $onSaleOnly = false): array
    {
        $page = $this->products->list((int) $request->query('page'), $request->query('sort'), $request->query('direction'), $request->query('q'), $onSaleOnly);
        $sort = in_array($request->query('sort'), ['name', 'price', 'quantity_available'], true) ? $request->query('sort') : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        return ['products' => $page, 'filters' => ['q' => trim($request->query('q')), 'sort' => $sort, 'direction' => $direction]];
    }

    /**
     * The details page with the purchase form and any errors.
     * @param array<string, string> $errors
     * @return Response
     */
    private function details(Product $product, string $quantity, array $errors): Response
    {
        return $this->view->render('products/show', ['title' => $product->name, 'product' => $product, 'quantity' => $quantity, 'errors' => $errors]);
    }

    /**
     * The add or edit form with the submitted values and any errors.
     * @param array<string, string> $input
     * @param array<string, string> $errors
     * @return Response
     */
    private function form(?Product $product, array $input, array $errors): Response
    {
        $title = $product === null ? 'Add product' : "Edit {$product->name}";

        return $this->view->render('admin/products/form', ['title' => $title, 'product' => $product, 'input' => $input, 'errors' => $errors]);
    }

    /**
     * The inventory page for the product with the restock form values and any errors.
     * @param array<string, string> $input
     * @param array<string, string> $errors
     * @return Response
     */
    private function inventoryPage(Request $request, int $id, array $input, array $errors): Response
    {
        $product = $this->products->find($id);
        $type = $request->query('type');

        return $this->view->render('admin/products/inventory', [
            'title' => "{$product->name} stock",
            'product' => $product,
            'movements' => $this->inventory->history($id, (int) $request->query('page'), $type),
            'type' => $type,
            'input' => $input,
            'errors' => $errors,
        ]);
    }

    /**
     * The signed-in admin; the route middleware guarantees one.
     * @return User
     */
    private function admin(): User
    {
        return $this->auth->user() ?? throw new AuthenticationException();
    }
}
