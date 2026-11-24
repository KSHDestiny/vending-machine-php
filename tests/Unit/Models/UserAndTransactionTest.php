<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\Role;
use App\Models\Transaction;
use App\Models\User;
use PHPUnit\Framework\TestCase;

final class UserAndTransactionTest extends TestCase
{
    public function testAdminsManageProductsAndCustomersBuy(): void
    {
        $admin = new User(1, 'Admin', 'admin@example.com', 'hash', Role::Admin);
        $customer = new User(2, 'Buyer', 'buyer@example.com', 'hash', Role::User);

        self::assertTrue($admin->canManageProducts());
        self::assertFalse($admin->canPurchase());
        self::assertTrue($customer->canPurchase());
        self::assertFalse($customer->canManageProducts());
        self::assertArrayNotHasKey('password', $customer->toArray());
    }

    public function testPurchaseIsVisibleToItsBuyerAndAdminsOnly(): void
    {
        $purchase = new Transaction(7, 2, 2, 1, '6.885', '6.885', 'now');

        self::assertTrue($purchase->isVisibleTo(new User(2, 'Buyer', 'b@example.com', 'hash', Role::User)));
        self::assertTrue($purchase->isVisibleTo(new User(1, 'Admin', 'a@example.com', 'hash', Role::Admin)));
        self::assertFalse($purchase->isVisibleTo(new User(3, 'Other', 'o@example.com', 'hash', Role::User)));
    }
}
