<?php

declare(strict_types=1);

/*
 * The composition root: builds every object once, by hand, and wires them through
 * constructors. No container, no globals. Tests call this with their own config, an
 * in-memory session store and, for unit tests, mocked repositories.
 */

use App\Controllers;
use App\Database\Database;
use App\Database\TransactionManager;
use App\Http\ErrorHandler;
use App\Http\Kernel;
use App\Http\Session\NativeSessionStore;
use App\Http\Routing\Router;
use App\Http\Session\Session;
use App\Auth\SessionAuth;
use App\Http\Session\SessionStore;
use App\Auth\TokenAuth;
use App\Http\View;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\ProductRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Services\AccountService;
use App\Services\AuthService;
use App\Services\DashboardService;
use App\Services\JwtService;
use App\Services\ProductService;
use App\Services\UserManagementService;
use App\Services\PurchaseService;
use App\Support\Logger;

/**
 * @param array{production: bool, db: array{host: string, port: int, name: string, user: string, password: string}, jwt: array{secret: string, ttl: int}, paths: array{views: string, migrations: string, log: string}} $config
 * @param array{products?: ProductRepositoryInterface, users?: UserRepositoryInterface, transactions?: TransactionRepositoryInterface, transactionManager?: TransactionManager} $overrides
 */
return static function (array $config, ?SessionStore $sessionStore = null, array $overrides = []): Kernel {
    // Infrastructure: the error log and the PDO connection (opened on first use).
    $logger = new Logger($config['paths']['log']);
    $database = Database::fromConfig($config['db']);

    // Repositories: the real PDO ones, unless a unit test passes mocks in $overrides.
    $productRepository = $overrides['products'] ?? new ProductRepository($database);
    $userRepository = $overrides['users'] ?? new UserRepository($database);
    $transactionRepository = $overrides['transactions'] ?? new TransactionRepository($database);

    // Services: the business rules, shared by the web pages and the API.
    $authService = new AuthService($userRepository);
    $accountService = new AccountService($userRepository);
    $productService = new ProductService($productRepository);
    $purchaseService = new PurchaseService($productRepository, $transactionRepository, $overrides['transactionManager'] ?? $database);
    $jwtService = new JwtService($config['jwt']['secret'], $config['jwt']['ttl']);
    $userManagement = new UserManagementService($userRepository, $transactionRepository, $overrides['transactionManager'] ?? $database);

    // HTTP: the session, the signed-in user (session for web, JWT for API) and the views.
    $session = new Session($sessionStore ?? new NativeSessionStore($config['production']));
    $sessionAuth = new SessionAuth($session, $authService);
    $tokenAuth = new TokenAuth($jwtService, $authService);
    $view = new View($config['paths']['views'], $session, $sessionAuth);

    // Web routes: browser pages behind the session, CSRF and role middleware.
    $router = new Router();
    (require dirname(__DIR__) . '/routes/web.php')(
        $router,
        $sessionAuth,
        $session,
        new Controllers\HealthController($database, $logger),
        new Controllers\AuthController($authService, $sessionAuth, $session, $view),
        new Controllers\AccountController($accountService, $sessionAuth, $session, $view),
        new Controllers\ProductsController($productService, $purchaseService, $sessionAuth, $session, $view),
        new Controllers\TransactionsController($purchaseService, $sessionAuth, $view),
        new Controllers\DashboardController(new DashboardService($productRepository, $transactionRepository), $view),
        new Controllers\AdminUsersController($userManagement, $sessionAuth, $session, $view),
    );

    // API routes: JSON endpoints behind the JWT middleware, outside the CSRF group.
    (require dirname(__DIR__) . '/routes/api.php')(
        $router,
        $tokenAuth,
        new Controllers\Api\AuthController($authService, $jwtService),
        new Controllers\Api\AccountController($accountService, $tokenAuth),
        new Controllers\Api\ProductsController($productService, $purchaseService, $tokenAuth),
        new Controllers\Api\TransactionsController($purchaseService, $tokenAuth),
    );

    // The kernel runs each request and turns any exception into an error response.
    return new Kernel($router, new ErrorHandler($view, $logger), $view);
};
