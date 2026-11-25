<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Enums\Role;
use App\Models\User;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Page;
use PHPUnit\Framework\TestCase;
use Tests\Support\Application;

/*
 * The app layout through the real routes: the sidebar shows each role only its own
 * pages, marks the current one, and guests get no sidebar.
 */
final class LayoutTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('paginate')->willReturn(new Page([], 0, 1, 10));
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('find')->willReturnCallback(static fn (int $id): ?User => [1 => self::admin(), 2 => self::customer()][$id] ?? null);

        $this->app = new Application(['products' => $products, 'users' => $users]);
    }

    public function testCustomerSeesOnlyCustomerPagesWithTheCurrentOneMarked(): void
    {
        $this->app->actingAs(self::customer());

        $body = $this->app->send('GET', '/products')->body;

        self::assertSame(['/products', '/my-transactions', '/account'], self::navLinks($body));
        self::assertSame(['/products'], self::currentLinks($body));
    }

    public function testAdminSeesAdminPagesAndTheSectionIsMarkedOnSubpages(): void
    {
        $this->app->actingAs(self::admin());

        $body = $this->app->send('GET', '/admin/products')->body;

        self::assertSame(['/admin', '/admin/products', '/admin/transactions', '/admin/users', '/products', '/account'], self::navLinks($body));
        self::assertSame(['/admin/products'], self::currentLinks($body));
    }

    public function testGuestPagesHaveNoSidebar(): void
    {
        $body = $this->app->send('GET', '/login')->body;

        self::assertSame([], self::navLinks($body));
        self::assertStringContainsString('action="/login"', $body);
    }

    /**
     * The hrefs of the main navigation, in order.
     * @return list<string>
     */
    private static function navLinks(string $body): array
    {
        preg_match_all('/<a data-nav href="([^"]+)"/', $body, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * The hrefs of navigation links marked as the current page.
     * @return list<string>
     */
    private static function currentLinks(string $body): array
    {
        preg_match_all('/<a data-nav href="([^"]+)"[^>]*aria-current="page"/', $body, $matches);

        return array_values(array_unique($matches[1]));
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
