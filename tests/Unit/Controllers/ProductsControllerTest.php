<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Models\Product;
use App\Enums\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Enums\ProductStatus;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Page;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Support\Application;
use Tests\Support\ImmediateTransactions;

/*
 * ProductsController through the real routes and middleware, with mocked
 * repositories injected, so no database is used.
 */
final class ProductsControllerTest extends TestCase
{
    /** @var ProductRepositoryInterface&MockObject */
    private ProductRepositoryInterface $products;

    /** @var TransactionRepositoryInterface&MockObject */
    private TransactionRepositoryInterface $transactions;

    private ImmediateTransactions $database;

    private Application $app;

    protected function setUp(): void
    {
        $this->products = $this->createMock(ProductRepositoryInterface::class);
        $this->transactions = $this->createMock(TransactionRepositoryInterface::class);
        $this->database = new ImmediateTransactions();

        $inventory = $this->createStub(InventoryRepositoryInterface::class);
        $inventory->method('paginateForProduct')->willReturn(new Page([], 0, 1, 15));
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('find')->willReturnCallback(static fn (int $id): ?User => [1 => self::admin(), 2 => self::customer()][$id] ?? null);

        $this->app = new Application(['products' => $this->products, 'transactions' => $this->transactions, 'users' => $users, 'inventory' => $inventory, 'transactionManager' => $this->database]);
    }

    public function testProductListReturnsTheProductsWithSearchSortAndPaging(): void
    {
        $this->transactions->expects(self::never())->method('create');
        $this->products->expects(self::once())
            ->method('paginate')
            ->with(2, 10, 'price', 'desc', 'pep', true)
            ->willReturn(new Page([self::pepsi()], 11, 2, 10));

        $this->app->actingAs(self::customer());
        $response = $this->app->send('GET', '/products', query: ['q' => 'pep', 'sort' => 'price', 'direction' => 'desc', 'page' => '2']);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Pepsi', $response->body);
        self::assertStringContainsString('$6.885', $response->body);
    }

    public function testUnknownSortColumnsFallBackToName(): void
    {
        $this->transactions->expects(self::never())->method('create');
        $this->products->expects(self::once())->method('paginate')->with(1, 10, 'name', 'asc', '')->willReturn(new Page([], 0, 1, 10));

        $this->app->actingAs(self::customer());
        self::assertSame(200, $this->app->send('GET', '/products', query: ['sort' => 'password', 'direction' => 'sideways'])->status);
    }

    public function testGuestsMustLogInToSeeProducts(): void
    {
        $this->transactions->expects(self::never())->method('create');
        $this->products->expects(self::never())->method('paginate');
        $this->products->expects(self::never())->method('find');

        foreach (['/', '/products', '/products/2'] as $path) {
            $response = $this->app->send('GET', $path);
            self::assertSame(303, $response->status, $path);
            self::assertSame('/login', $response->headers['Location'], $path);
        }
    }

    public function testMissingProductReturnsNotFound(): void
    {
        $this->transactions->expects(self::never())->method('create');
        $this->products->expects(self::once())->method('find')->with(99)->willReturn(null);

        $this->app->actingAs(self::customer());
        $response = $this->app->send('GET', '/products/99');

        self::assertSame(404, $response->status);
        self::assertStringContainsString('Product not found.', $response->body);
    }

    public function testCustomersCannotOpenAProductOffSaleButAdminsCan(): void
    {
        $this->products->expects(self::exactly(2))->method('find')->with(2)->willReturn(new Product(2, 'Pepsi', '6.885', 10, ProductStatus::Inactive));
        $this->transactions->expects(self::never())->method('create');

        $this->app->actingAs(self::customer());
        self::assertSame(404, $this->app->send('GET', '/products/2')->status);
        $this->app->actingAs(self::admin());
        self::assertSame(200, $this->app->send('GET', '/products/2')->status);
    }

    public function testAdminRestocksAProduct(): void
    {
        $this->products->method('findForUpdate')->willReturn(self::pepsi(4));
        $this->products->method('find')->willReturn(self::pepsi(14));
        $this->products->expects(self::once())->method('setStock')->with(2, 14);
        $this->transactions->expects(self::never())->method('create');

        $this->app->actingAs(self::admin());
        $response = $this->app->submit('POST', '/admin/products/2/restock', ['quantity' => '10', 'reason' => 'Delivery']);

        self::assertSame(303, $response->status);
        self::assertSame('/admin/products/2/inventory', $response->headers['Location']);
    }

    public function testInvalidRestockShowsTheErrorAndCustomersCannotRestock(): void
    {
        $this->products->method('find')->willReturn(self::pepsi(4));
        $this->products->expects(self::never())->method('setStock');
        $this->transactions->expects(self::never())->method('create');

        $this->app->actingAs(self::admin());
        $response = $this->app->submit('POST', '/admin/products/2/restock', ['quantity' => '0']);
        self::assertSame('/admin/products/2/inventory', $response->headers['Location'] ?? null);
        self::assertStringContainsString('Quantity must be', $this->app->follow($response)->body);

        $this->app->actingAs(self::customer());
        self::assertSame(403, $this->app->submit('POST', '/admin/products/2/restock', ['quantity' => '5'])->status);
        self::assertSame(403, $this->app->send('GET', '/admin/products/2/inventory')->status);
    }

    public function testProductCreationSucceedsWithValidInput(): void
    {
        $this->transactions->expects(self::never())->method('create');
        $this->app->actingAs(self::admin());
        $this->products->expects(self::once())->method('create')->with('Tea', '1.250', 3)->willReturn(new Product(9, 'Tea', '1.250', 3));

        $response = $this->app->submit('POST', '/admin/products', ['name' => ' Tea ', 'price' => '1.250', 'quantity_available' => '3']);

        self::assertSame(303, $response->status);
        self::assertSame('/admin/products', $response->headers['Location']);
    }

    public function testInvalidPriceIsRejected(): void
    {
        $this->transactions->expects(self::never())->method('create');
        $this->app->actingAs(self::admin());
        $this->products->expects(self::never())->method('create');

        $response = $this->app->submit('POST', '/admin/products', ['name' => 'Tea', 'price' => '0', 'quantity_available' => '3']);
        $page = $this->app->follow($response)->body;

        self::assertSame('/admin/products/create', $response->headers['Location'] ?? null);
        self::assertStringContainsString('Price must be greater than 0', $page);
        self::assertStringContainsString('value="Tea"', $page, 'the values entered are kept');
    }

    public function testNegativeInventoryIsRejected(): void
    {
        $this->transactions->expects(self::never())->method('create');
        $this->app->actingAs(self::admin());
        $this->products->expects(self::never())->method('create');

        $response = $this->app->submit('POST', '/admin/products', ['name' => 'Tea', 'price' => '1.00', 'quantity_available' => '-1']);

        self::assertSame('/admin/products/create', $response->headers['Location'] ?? null);
        self::assertStringContainsString('Quantity available must be a whole number of 0 or more.', $this->app->follow($response)->body);
    }

    public function testProductUpdateWorks(): void
    {
        $this->transactions->expects(self::never())->method('create');
        $this->app->actingAs(self::admin());
        $this->products->method('find')->willReturn(self::pepsi());
        $this->products->method('findForUpdate')->willReturn(self::pepsi());
        $this->products->expects(self::once())->method('update')->with(2, 'Pepsi Max', '7.000', ProductStatus::Active);
        $this->products->expects(self::once())->method('setStock')->with(2, 50);

        $response = $this->app->submit('PUT', '/admin/products/2', ['name' => 'Pepsi Max', 'price' => '7.000', 'quantity_available' => '50']);

        self::assertSame(303, $response->status);
    }

    public function testProductDeletionWorks(): void
    {
        $this->transactions->expects(self::never())->method('create');
        $this->app->actingAs(self::admin());
        $this->products->method('find')->willReturn(self::pepsi());
        $this->products->expects(self::once())->method('delete')->with(2);

        self::assertSame(303, $this->app->submit('DELETE', '/admin/products/2')->status);
    }

    public function testNonAdminCannotManageProducts(): void
    {
        $this->transactions->expects(self::never())->method('create');
        $this->app->actingAs(self::customer());
        $this->products->expects(self::never())->method('create');

        $response = $this->app->submit('POST', '/admin/products', ['name' => 'Tea', 'price' => '1', 'quantity_available' => '1']);

        self::assertSame(403, $response->status);
        self::assertSame(403, $this->app->send('GET', '/admin/products')->status);
    }

    public function testFormPostWithoutCsrfTokenIsRejected(): void
    {
        $this->transactions->expects(self::never())->method('create');
        $this->app->actingAs(self::admin());
        $this->products->expects(self::never())->method('create');

        self::assertSame(403, $this->app->send('POST', '/admin/products', ['name' => 'Tea', 'price' => '1', 'quantity_available' => '1'])->status);
    }

    public function testSuccessfulPurchaseReducesStockAndRecordsTheTransactionAtTheDatabasePrice(): void
    {
        $this->app->actingAs(self::customer());
        $this->products->expects(self::once())->method('findForUpdate')->with(2)->willReturn(self::pepsi(stock: 5));
        $this->products->expects(self::once())->method('decrementStock')->with(2, 2);
        $this->transactions->expects(self::once())
            ->method('create')
            ->with(2, 2, 'Pepsi', 2, '6.885', '13.770')
            ->willReturn(new Transaction(7, 2, 2, 2, '6.885', '13.770', '2026-01-01 00:00:00', 'Pepsi'));

        // A price sent by the browser is ignored: the price always comes from the database.
        $response = $this->app->submit('POST', '/products/2/purchase', ['quantity' => '2', 'price' => '0.001']);

        self::assertSame(303, $response->status);
        self::assertSame('/transactions/7', $response->headers['Location']);
        self::assertSame(1, $this->database->count);
    }

    public function testInsufficientStockRejectsThePurchase(): void
    {
        $this->app->actingAs(self::customer());
        $this->products->method('find')->willReturn(self::pepsi(stock: 1));
        $this->products->method('findForUpdate')->willReturn(self::pepsi(stock: 1));
        $this->products->expects(self::never())->method('decrementStock');
        $this->transactions->expects(self::never())->method('create');

        $response = $this->app->submit('POST', '/products/2/purchase', ['quantity' => '2']);
        $page = $this->app->follow($response)->body;

        self::assertSame('/products/2', $response->headers['Location'] ?? null);
        self::assertStringContainsString('Only 1 left in stock.', $page);
        self::assertStringContainsString('value="2"', $page, 'the quantity entered is kept');
    }

    public function testZeroOrNegativePurchaseQuantityIsRejected(): void
    {
        $this->transactions->expects(self::never())->method('create');
        $this->app->actingAs(self::customer());
        $this->products->method('find')->willReturn(self::pepsi());
        $this->products->expects(self::never())->method('findForUpdate');

        foreach (['0', '-3', '1.5', ''] as $quantity) {
            $response = $this->app->submit('POST', '/products/2/purchase', ['quantity' => $quantity]);
            self::assertSame('/products/2', $response->headers['Location'] ?? null, "quantity '{$quantity}'");
            self::assertStringContainsString('Quantity', $this->app->follow($response)->body);
        }
    }

    public function testUnauthenticatedPurchaseIsRejected(): void
    {
        $this->transactions->expects(self::never())->method('create');
        $this->products->expects(self::never())->method('findForUpdate');

        $response = $this->app->submit('POST', '/products/2/purchase', ['quantity' => '1']);

        self::assertSame(303, $response->status);
        self::assertSame('/login', $response->headers['Location']);
    }

    public function testAdminCannotPurchase(): void
    {
        $this->transactions->expects(self::never())->method('create');
        $this->app->actingAs(self::admin());
        $this->products->expects(self::never())->method('findForUpdate');

        self::assertSame(403, $this->app->submit('POST', '/products/2/purchase', ['quantity' => '1'])->status);
    }

    /**
     * The seeded admin.
     * @return User
     */
    private static function admin(): User
    {
        return new User(1, 'Admin', 'admin@example.com', 'hash', Role::Admin);
    }

    /**
     * A customer account.
     * @return User
     */
    private static function customer(): User
    {
        return new User(2, 'Buyer', 'buyer@example.com', 'hash', Role::User);
    }

    /**
     * Pepsi at 6.885 with the given stock.
     * @return Product
     */
    private static function pepsi(int $stock = 10): Product
    {
        return new Product(2, 'Pepsi', '6.885', $stock);
    }
}
