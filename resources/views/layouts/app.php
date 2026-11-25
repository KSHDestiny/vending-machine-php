<?php
/**
 * The page shell. Signed-in users get a sidebar (a menu on small screens) with only
 * their role's pages; guests get a centred card. Links are shown per role for
 * convenience only: every route checks the role on the server.
 *
 * @var callable(string|int|float|null): string $e
 * @var callable(string, array<string, mixed>): string $partial
 * @var string $csrf
 * @var ?App\Models\User $currentUser
 * @var string $currentPath
 * @var string $title
 * @var ?string $flash
 * @var string $content
 */
// The file's change time as a version, so browsers reload the CSS and JS whenever they change.
// A missing built file is a packaging mistake, so it fails loudly instead of linking nothing.
$asset = static function (string $path): string {
    $file = dirname(__DIR__, 3) . '/public' . $path;
    if (!is_file($file)) {
        throw new RuntimeException("Asset {$path} is missing from public/");
    }

    return $path . '?v=' . filemtime($file);
};

// Each link is current on its own path and below it (/admin/products/2/edit is under Products).
$isAdmin = $currentUser?->canManageProducts() ?? false;
$nav = $isAdmin
    ? [
        ['/admin', 'Dashboard', 'dashboard', []],
        ['/admin/products', 'Products', 'cube', ['/admin/products/']],
        ['/admin/transactions', 'Transactions', 'receipt', ['/transactions/']],
        ['/admin/users', 'Users', 'users', ['/admin/users/']],
        ['/products', 'Storefront', 'bag', ['/', '/products/']],
    ]
    : [
        ['/products', 'Products', 'bag', ['/', '/products/']],
        ['/my-transactions', 'My purchases', 'receipt', ['/transactions/']],
    ];
$account = ['/account', 'Account', 'user', []];
$isCurrent = static function (array $item) use ($currentPath): bool {
    [$href, , , $below] = $item;
    foreach ($below as $prefix) {
        if ($prefix === '/' ? $currentPath === '/' : str_starts_with($currentPath, $prefix)) {
            return true;
        }
    }

    return $currentPath === $href;
};
$link = static function (array $item) use ($e, $partial, $isCurrent): string {
    [$href, $label, $icon] = $item;
    $current = $isCurrent($item);
    $style = $current ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900';
    $iconStyle = $current ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-600';

    return '<a data-nav href="' . $e($href) . '" class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium ' . $style . '"'
        . ($current ? ' aria-current="page"' : '') . '>'
        . $partial('icon', ['name' => $icon, 'class' => "h-5 w-5 shrink-0 {$iconStyle}"]) . $e($label) . '</a>';
};
$brand = '<a href="' . ($isAdmin ? '/admin' : '/products') . '" class="flex items-center gap-2.5">'
    . '<span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-white">' . $partial('icon', ['name' => 'cube', 'class' => 'h-5 w-5']) . '</span>'
    . '<span class="text-base font-semibold text-slate-900">Vending Machine</span></a>';
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
<body class="min-h-full bg-linear-to-br from-slate-50 via-white to-indigo-50/60 text-slate-800 antialiased">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:shadow">Skip to content</a>
<?php if ($currentUser !== null): ?>
    <?php
    $userCard = '<div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">'
        . '<span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">' . $e(mb_strtoupper(mb_substr($currentUser->name, 0, 1))) . '</span>'
        . '<span class="min-w-0"><span class="block truncate text-sm font-medium text-slate-900">' . $e($currentUser->name) . '</span>'
        . '<span class="block truncate text-xs text-slate-500">' . $e($isAdmin ? 'Admin' : 'Customer') . ' · ' . $e($currentUser->email) . '</span></span></div>';
    $logout = '<form method="post" action="/logout">' . $csrf
        . '<button type="submit" class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900">'
        . $partial('icon', ['name' => 'logout', 'class' => 'h-5 w-5 shrink-0 text-slate-400 group-hover:text-slate-600']) . 'Log out</button></form>';
    ?>
    <aside class="hidden border-r border-slate-200 bg-white/90 backdrop-blur lg:fixed lg:inset-y-0 lg:flex lg:w-64 lg:flex-col">
        <div class="flex h-16 items-center px-6"><?= $brand ?></div>
        <nav aria-label="Main" class="flex flex-1 flex-col gap-1 overflow-y-auto px-3 py-4">
            <?php foreach ($nav as $item): ?><?= $link($item) ?><?php endforeach ?>
        </nav>
        <div class="space-y-1 border-t border-slate-200 px-3 py-4">
            <?= $link($account) ?>
            <?= $logout ?>
            <div class="pt-2"><?= $userCard ?></div>
        </div>
    </aside>

    <div class="lg:pl-64">
        <details class="group border-b border-slate-200 bg-white lg:hidden">
            <summary class="flex h-16 cursor-pointer list-none items-center justify-between px-4 [&::-webkit-details-marker]:hidden">
                <?= $brand ?>
                <span class="rounded-md p-2 text-slate-600 group-open:bg-slate-100"><?= $partial('icon', ['name' => 'menu', 'class' => 'h-6 w-6']) ?><span class="sr-only">Menu</span></span>
            </summary>
            <nav aria-label="Main (mobile)" class="space-y-1 border-t border-slate-200 px-3 py-3">
                <?php foreach ([...$nav, $account] as $item): ?><?= $link($item) ?><?php endforeach ?>
                <?= $logout ?>
                <div class="pt-2"><?= $userCard ?></div>
            </nav>
        </details>

        <main id="main" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-10 lg:py-10">
            <?php if ($flash !== null): ?>
                <p class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status"><?= $e($flash) ?></p>
            <?php endif ?>
            <?= $content ?>
        </main>
    </div>
<?php else: ?>
    <main id="main" class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
        <div class="mb-8"><?= $brand ?></div>
        <div class="w-full max-w-md">
            <?php if ($flash !== null): ?>
                <p class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status"><?= $e($flash) ?></p>
            <?php endif ?>
            <?= $content ?>
        </div>
    </main>
<?php endif ?>
</body>
</html>
