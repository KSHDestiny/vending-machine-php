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
    $style = $active ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-100';

    return '<a href="' . $e($url(['sort' => $column, 'direction' => $direction, 'page' => 1])) . '" class="rounded-md px-3 py-1.5 text-sm ' . $style . '">' . $e($label) . $arrow . '</a>';
};
?>
<div class="flex flex-wrap items-end justify-between gap-4">
    <h1 class="text-2xl font-semibold text-slate-900">Products</h1>
    <form method="get" action="/products" class="flex gap-2" role="search">
        <input type="hidden" name="sort" value="<?= $e($filters['sort']) ?>">
        <input type="hidden" name="direction" value="<?= $e($filters['direction']) ?>">
        <input type="search" name="q" value="<?= $e($filters['q']) ?>" placeholder="Search by name" maxlength="100" aria-label="Search by name"
               class="w-56 rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <button type="submit" class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Search</button>
    </form>
</div>

<div class="mt-4 flex items-center gap-1 text-sm">
    <span class="mr-1 text-slate-500">Sort by</span>
    <?= $sortLink('name', 'Name') ?>
    <?= $sortLink('price', 'Price') ?>
    <?= $sortLink('quantity_available', 'Stock') ?>
</div>

<?php if ($products->items === []): ?>
    <p class="mt-8 rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500">
        <?= $filters['q'] === '' ? 'No products yet.' : 'No products match "' . $e($filters['q']) . '".' ?>
    </p>
<?php else: ?>
    <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($products->items as $product): ?>
            <li class="flex flex-col rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900"><?= $e($product->name) ?></h2>
                <p class="mt-1 text-2xl font-bold text-indigo-600">$<?= $e($money($product->price)) ?></p>
                <p class="mt-1 text-sm <?= !$product->isSoldOut() ? 'text-slate-500' : 'text-red-600' ?>">
                    <?= !$product->isSoldOut() ? $e($product->quantityAvailable) . ' in stock' : 'Sold out' ?>
                </p>
                <a href="/products/<?= $e($product->id) ?>" class="mt-4 inline-block rounded-md border border-indigo-600 px-4 py-2 text-center text-sm font-medium text-indigo-600 hover:bg-indigo-50">View details</a>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>

<?= $partial('pagination', ['e' => $e, 'page' => $products, 'url' => static fn (int $page): string => $url(['page' => $page])]) ?>
