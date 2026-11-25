<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Api;

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
 * The JSON API through the real routes and JWT middleware, with mocked repositories.
 */
final class ProductsControllerTest extends TestCase
{
    /** @var ProductRepositoryInterface&MockObject */
    private ProductRepositoryInterface $products;

    /** @var TransactionRepositoryInterface&MockObject */
    private TransactionRepositoryInterface $transactions;

    private Application $app;

    protected function setUp(): void
    {
        $this->products = $this->createMock(ProductRepositoryInterface::class);
        $this->transactions = $this->createMock(TransactionRepositoryInterface::class);
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('find')->willReturnCallback(static fn (int $id): ?User => [1 => self::admin(), 2 => self::customer()][$id] ?? null);

        $this->app = new Application(['products' => $this->products, 'transactions' => $this->transactions, 'users' => $users, 'inventory' => $this->createStub(InventoryRepositoryInterface::class), 'transactionManager' => new ImmediateTransactions()]);
    }

    public function testProductListNeedsATokenAndReturnsJsonWithPagination(): void
    {
        $this->products->method('paginate')->willReturn(new Page([new Product(2, 'Pepsi', '6.885', 4)], 1, 1, 10));

        self::assertSame(401, $this->app->api('GET', '/api/products')->status);

        $response = $this->app->api('GET', '/api/products', $this->app->tokenFor(self::customer()));

        self::assertSame(200, $response->status);
        self::assertSame([
            'data' => [['id' => 2, 'name' => 'Pepsi', 'price' => '6.885', 'quantity_available' => 4, 'status' => 'active']],
            'meta' => ['page' => 1, 'per_page' => 10, 'total' => 1, 'last_page' => 1],
        ], Application::json($response));
    }

    public function testOnlyAdminTokensListProductsOffSale(): void
    {
        $this->transactions->expects(self::never())->method('create');
        $this->products->expects(self::exactly(2))
            ->method('paginate')
            ->willReturnCallback(static function (int $page, int $perPage, string $sort, string $direction, string $search, bool $onSaleOnly): Page {
                return new Page($onSaleOnly ? [] : [new Product(3, 'Old Cola', '1.000', 0, ProductStatus::Inactive)], $onSaleOnly ? 0 : 1, 1, 10);
            });

        self::assertSame([], Application::json($this->app->api('GET', '/api/products', $this->app->tokenFor(self::customer())))['data']);
        self::assertCount(1, Application::json($this->app->api('GET', '/api/products', $this->app->tokenFor(self::admin())))['data']);
    }

    public function testManagingProductsNeedsAnAdminToken(): void
    {
        $this->products->expects(self::never())->method('create');
        $body = ['name' => 'Tea', 'price' => '1.25', 'quantity_available' => 3];

        self::assertSame(401, $this->app->api('POST', '/api/products', null, $body)->status);
        self::assertSame(401, $this->app->api('POST', '/api/products', 'not-a-jwt', $body)->status);
        self::assertSame(403, $this->app->api('POST', '/api/products', $this->app->tokenFor(self::customer()), $body)->status);
    }

    public function testAdminCreatesAProduct(): void
    {
        $this->products->expects(self::once())->method('create')->with('Tea', '1.25', 3)->willReturn(new Product(9, 'Tea', '1.250', 3));

        // JSON numbers, as an API client would send them.
        $response = $this->app->api('POST', '/api/products', $this->app->tokenFor(self::admin()), ['name' => 'Tea', 'price' => 1.25, 'quantity_available' => 3]);

        self::assertSame(201, $response->status);
        self::assertSame('/api/products/9', $response->headers['Location']);
    }

    public function testValidationErrorsAreJson422(): void
    {
        $response = $this->app->api('POST', '/api/products', $this->app->tokenFor(self::admin()), ['name' => '', 'price' => -1, 'quantity_available' => 1.5]);

        self::assertSame(422, $response->status);
        self::assertSame(['name', 'price', 'quantity_available'], array_keys((array) Application::json($response)['errors']));
    }

    public function testPurchaseUsesTheDatabasePriceNotTheRequest(): void
    {
        $this->products->method('findForUpdate')->willReturn(new Product(2, 'Pepsi', '6.885', 5));
        $this->transactions->expects(self::once())
            ->method('create')
            ->with(2, 2, 'Pepsi', 2, '6.885', '13.770')
            ->willReturn(new Transaction(7, 2, 2, 2, '6.885', '13.770', '2026-01-01 00:00:00', 'Pepsi'));

        $response = $this->app->api('POST', '/api/products/2/purchase', $this->app->tokenFor(self::customer()), ['quantity' => 2, 'price' => '0.001']);

        self::assertSame(201, $response->status);
        self::assertSame('13.770', ((array) Application::json($response)['data'])['total_price']);
    }

    public function testMissingProductIsJson404(): void
    {
        $this->products->method('find')->willReturn(null);

        $response = $this->app->api('GET', '/api/products/99', $this->app->tokenFor(self::customer()));

        self::assertSame(404, $response->status);
        self::assertSame(['message' => 'Product not found.'], Application::json($response));
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
}
