<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var string $csrf
 * @var ?App\Models\User $currentUser
 * @var string $title
 * @var ?string $flash
 * @var string $content
 */
$link = 'rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900';
// The file's change time as a version, so browsers reload the CSS and JS whenever they change.
// A missing built file is a packaging mistake, so it fails loudly instead of linking nothing.
$asset = static function (string $path): string {
    $file = dirname(__DIR__, 3) . '/public' . $path;
    if (!is_file($file)) {
        throw new RuntimeException("Asset {$path} is missing from public/");
    }

    return $path . '?v=' . filemtime($file);
};
?>
<!doctype html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($title) ?> · Vending Machine</title>
    <link rel="stylesheet" href="<?= $e($asset('/assets/app.css')) ?>">
    <script src="<?= $e($asset('/assets/app.js')) ?>" defer></script>
</head>
<body class="h-full text-slate-800 antialiased">
<header class="border-b border-slate-200 bg-white">
    <nav class="mx-auto flex max-w-6xl flex-wrap items-center gap-1 px-4 py-3">
        <a href="/products" class="mr-4 text-lg font-semibold text-slate-900">Vending Machine</a>
        <?php if ($currentUser !== null): ?>
            <a href="/products" class="<?= $link ?>">Products</a>
        <?php endif ?>
        <?php if ($currentUser?->canManageProducts()): ?>
            <a href="/admin" class="<?= $link ?>">Dashboard</a>
            <a href="/admin/products" class="<?= $link ?>">Manage products</a>
            <a href="/admin/transactions" class="<?= $link ?>">Transactions</a>
        <?php elseif ($currentUser !== null): ?>
            <a href="/my-transactions" class="<?= $link ?>">My purchases</a>
        <?php endif ?>
        <div class="ml-auto flex items-center gap-2">
            <?php if ($currentUser !== null): ?>
                <a href="/account" class="flex items-center gap-2 rounded-md px-2 py-1 text-sm font-medium text-slate-700 hover:bg-slate-100" title="My account">
                    <?= $e($currentUser->name) ?>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $currentUser->canManageProducts() ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700' ?>"><?= $e(ucfirst($currentUser->role->value)) ?></span>
                </a>
                <form method="post" action="/logout">
                    <?= $csrf ?>
                    <button type="submit" class="<?= $link ?>">Log out</button>
                </form>
            <?php else: ?>
                <a href="/login" class="<?= $link ?>">Log in</a>
                <a href="/register" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Register</a>
            <?php endif ?>
        </div>
    </nav>
</header>
<main class="mx-auto max-w-6xl px-4 py-8">
    <?php if ($flash !== null): ?>
        <p class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status"><?= $e($flash) ?></p>
    <?php endif ?>
    <?= $content ?>
</main>
</body>
</html>
