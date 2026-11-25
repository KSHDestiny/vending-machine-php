<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AuthenticationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Product;
use App\Enums\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Services\InventoryService;
use App\Services\PurchaseService;
use PHPUnit\Framework\TestCase;
use Tests\Support\ImmediateTransactions;

final class PurchaseServiceTest extends TestCase
{
    public function testTotalUsesExactDecimalArithmetic(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('findForUpdate')->willReturn(new Product(2, 'Pepsi', '6.885', 10));
        $transactions = $this->createMock(TransactionRepositoryInterface::class);
        $transactions->expects(self::once())
            ->method('create')
            ->with(5, 2, 3, '6.885', '20.655')
            ->willReturn(new Transaction(1, 5, 2, 3, '6.885', '20.655', 'now'));

        (new PurchaseService($products, $transactions, $this->inventory($products), new ImmediateTransactions()))->purchase(self::buyer(), 2, '3');
    }

    public function testGuestCannotPurchase(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->service()->purchase(null, 2, '1');
    }

    public function testMissingProductIsNotFound(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('findForUpdate')->willReturn(null);

        $this->expectException(NotFoundException::class);

        (new PurchaseService($products, $this->createStub(TransactionRepositoryInterface::class), $this->inventory($products), new ImmediateTransactions()))->purchase(self::buyer(), 99, '1');
    }

    public function testOrderTotalLargerThanTheColumnIsRejected(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('findForUpdate')->willReturn(new Product(2, 'Gold', '9999999.999', 999_999_999));

        $this->expectException(ValidationException::class);

        (new PurchaseService($products, $this->createStub(TransactionRepositoryInterface::class), $this->inventory($products), new ImmediateTransactions()))->purchase(self::buyer(), 2, '2');
    }

    /**
     * A service with stub repositories.
     * @return PurchaseService
     */
    private function service(): PurchaseService
    {
        $products = $this->createStub(ProductRepositoryInterface::class);

        return new PurchaseService($products, $this->createStub(TransactionRepositoryInterface::class), $this->inventory($products), new ImmediateTransactions());
    }

    /**
     * A customer.
     * @return User
     */
    private static function buyer(): User
    {
        return new User(5, 'Buyer', 'buyer@example.com', 'hash', Role::User);
    }

    /**
     * Inventory on the same product repository, with movements going nowhere.
     * @return InventoryService
     */
    private function inventory(ProductRepositoryInterface $products): InventoryService
    {
        return new InventoryService($products, $this->createStub(InventoryRepositoryInterface::class), new ImmediateTransactions());
    }
}
