<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string): string $money
 * @var callable(string, array<string, mixed>): string $partial
 * @var int $products
 * @var list<App\Models\Product> $low_stock
 * @var array{count: int, items_sold: int, revenue: string} $sales
 * @var list<App\Models\Transaction> $latest
 */
$cards = [
    'Revenue' => '$' . $money($sales['revenue']),
    'Purchases' => (string) $sales['count'],
    'Items sold' => (string) $sales['items_sold'],
    'Products' => (string) $products,
];
?>
<h1 class="text-2xl font-semibold text-slate-900">Dashboard</h1>

<dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ($cards as $label => $value): ?>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <dt class="text-sm text-slate-500"><?= $e($label) ?></dt>
            <dd class="mt-1 text-2xl font-semibold text-slate-900"><?= $e($value) ?></dd>
        </div>
    <?php endforeach ?>
</dl>

<h2 class="mt-10 text-lg font-semibold text-slate-900">Low stock (<?= $e(App\Services\DashboardService::LOW_STOCK) ?> or fewer)</h2>
<?php if ($low_stock === []): ?>
    <p class="mt-2 text-sm text-slate-500">Every product has enough stock.</p>
<?php else: ?>
    <ul class="mt-3 divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white text-sm shadow-sm">
        <?php foreach ($low_stock as $product): ?>
            <li class="flex items-center justify-between px-4 py-3">
                <span class="font-medium text-slate-900"><?= $e($product->name) ?></span>
                <span class="<?= $product->isSoldOut() ? 'text-red-600' : 'text-amber-600' ?>"><?= $e($product->quantityAvailable) ?> left</span>
                <a href="/admin/products/<?= $e($product->id) ?>/edit" class="text-indigo-600 hover:underline">Restock</a>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>

<div class="mt-10 flex items-center justify-between">
    <h2 class="text-lg font-semibold text-slate-900">Latest purchases</h2>
    <a href="/admin/transactions" class="text-sm text-indigo-600 hover:underline">View all</a>
</div>
<div class="mt-3">
    <?php if ($latest === []): ?>
        <p class="text-sm text-slate-500">No purchases yet.</p>
    <?php else: ?>
        <?= $partial('transactions-table', ['e' => $e, 'money' => $money, 'transactions' => $latest, 'showBuyer' => true]) ?>
    <?php endif ?>
</div>
