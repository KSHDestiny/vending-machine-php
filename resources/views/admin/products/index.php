<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string): string $money
 * @var callable(string, array<string, mixed>): string $partial
 * @var string $csrf
 * @var App\Support\Page<App\Models\Product> $products
 * @var array{q: string, sort: string, direction: string} $filters
 */
$url = static fn (array $params): string => '/admin/products?' . http_build_query($params + $filters + ['page' => $products->page]);
$sortLink = static function (string $column, string $label) use ($e, $url, $filters): string {
    $active = $filters['sort'] === $column;
    $direction = $active && $filters['direction'] === 'asc' ? 'desc' : 'asc';
    $arrow = $active ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '';

    return '<a href="' . $e($url(['sort' => $column, 'direction' => $direction, 'page' => 1])) . '" class="hover:text-slate-900">' . $e($label) . $arrow . '</a>';
};
$actions = '<form method="get" action="/admin/products" role="search">'
    . '<input type="search" name="q" value="' . $e($filters['q']) . '" placeholder="Search by name" maxlength="100" aria-label="Search by name"'
    . ' class="w-56 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"></form>'
    . '<a href="/admin/products/create" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">'
    . $partial('icon', ['name' => 'plus', 'class' => 'h-4 w-4']) . 'Add product</a>';
?>
<?= $partial('page-header', ['title' => 'Products', 'description' => 'Prices, stock and the catalogue customers see.', 'actions' => $actions]) ?>

<div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
        <tr>
            <th class="px-4 py-3 font-medium"><?= $sortLink('name', 'Name') ?></th>
            <th class="px-4 py-3 text-right font-medium"><?= $sortLink('price', 'Price') ?></th>
            <th class="px-4 py-3 text-right font-medium"><?= $sortLink('quantity_available', 'Stock') ?></th>
            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
        </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($products->items as $product): ?>
            <tr>
                <td class="px-4 py-3 font-medium text-slate-900"><?= $e($product->name) ?></td>
                <td class="px-4 py-3 text-right">$<?= $e($money($product->price)) ?></td>
                <td class="px-4 py-3 text-right"><?= $partial('stock-badge', ['product' => $product]) ?></td>
                <td class="px-4 py-3 text-right">
                    <a href="/admin/products/<?= $e($product->id) ?>/edit" class="font-medium text-indigo-600 hover:underline">Edit</a>
                    <form method="post" action="/admin/products/<?= $e($product->id) ?>" class="ml-3 inline" data-confirm="Delete <?= $e($product->name) ?>? Its purchase history is kept.">
                        <?= $csrf ?>
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="font-medium text-red-600 hover:underline">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach ?>
        <?php if ($products->items === []): ?>
            <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No products found.</td></tr>
        <?php endif ?>
        </tbody>
    </table>
</div>

<?= $partial('pagination', ['page' => $products, 'url' => static fn (int $page): string => $url(['page' => $page])]) ?>
