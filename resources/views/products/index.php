<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string): string $money
 * @var callable(string, array<string, mixed>): string $partial
 * @var App\Support\Page<App\Models\Product> $products
 * @var array{q: string, sort: string, direction: string} $filters
 */
$url = static fn (array $params): string => '/products?' . http_build_query($params + $filters + ['page' => $products->page]);
// Clicking the current sort column flips the direction; a new column starts ascending.
$sortLink = static function (string $column, string $label) use ($e, $url, $filters): string {
    $active = $filters['sort'] === $column;
    $direction = $active && $filters['direction'] === 'asc' ? 'desc' : 'asc';
    $arrow = $active ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '';
    $style = $active ? 'bg-white text-indigo-700 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:text-slate-900';

    return '<a href="' . $e($url(['sort' => $column, 'direction' => $direction, 'page' => 1])) . '" class="rounded-md px-3 py-1.5 text-sm font-medium ' . $style . '"'
        . ($active ? ' aria-current="true"' : '') . '>' . $e($label) . $arrow . '</a>';
};
$search = '<form method="get" action="/products" class="flex gap-2" role="search">'
    . '<input type="hidden" name="sort" value="' . $e($filters['sort']) . '">'
    . '<input type="hidden" name="direction" value="' . $e($filters['direction']) . '">'
    . '<input type="search" name="q" value="' . $e($filters['q']) . '" placeholder="Search by name" maxlength="100" aria-label="Search by name"'
    . ' class="w-56 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">'
    . '<button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">'
    . $partial('icon', ['name' => 'search', 'class' => 'h-4 w-4']) . 'Search</button></form>';
?>
<?= $partial('page-header', ['title' => 'Products', 'description' => 'Pick a drink and buy it in a few clicks.', 'actions' => $search]) ?>

<div class="mt-6 inline-flex items-center gap-1 rounded-lg bg-slate-100 p-1 text-sm">
    <span class="px-2 text-slate-500">Sort by</span>
    <?= $sortLink('name', 'Name') ?>
    <?= $sortLink('price', 'Price') ?>
    <?= $sortLink('quantity_available', 'Stock') ?>
</div>

<?php if ($products->items === []): ?>
    <p class="mt-8 rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
        <?= $filters['q'] === '' ? 'No products yet.' : 'No products match "' . $e($filters['q']) . '".' ?>
    </p>
<?php else: ?>
    <ul class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($products->items as $product): ?>
            <li class="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"><?= $partial('icon', ['name' => 'bag', 'class' => 'h-6 w-6']) ?></span>
                    <?= $partial('stock-badge', ['product' => $product]) ?>
                </div>
                <h2 class="mt-4 text-lg font-semibold text-slate-900"><?= $e($product->name) ?></h2>
                <p class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">$<?= $e($money($product->price)) ?></p>
                <a href="/products/<?= $e($product->id) ?>" class="mt-5 inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:border-indigo-500 hover:text-indigo-700">View details</a>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>

<?= $partial('pagination', ['page' => $products, 'url' => static fn (int $page): string => $url(['page' => $page])]) ?>
