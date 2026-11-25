<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string): string $money
 * @var callable(string, array<string, mixed>): string $partial
 * @var int $products
 * @var list<App\Models\Product> $low_stock
 * @var array{count: int, items_sold: int, revenue: string} $sales
 * @var list<App\Models\Transaction> $latest
 * @var array{days: list<array{date: string, count: int, revenue: string}>, count: int, revenue: string} $trend
 * @var int $days
 */
$threshold = App\Services\DashboardService::LOW_STOCK;
$cards = [
    ['Revenue', '$' . $money($sales['revenue']), 'From ' . $sales['count'] . ($sales['count'] === 1 ? ' purchase' : ' purchases'), 'money', 'emerald'],
    ['Purchases', (string) $sales['count'], $sales['items_sold'] . ($sales['items_sold'] === 1 ? ' item sold' : ' items sold'), 'cart', 'indigo'],
    ['Products', (string) $products, 'On sale in the catalogue', 'cube', 'sky'],
    ['Low stock', (string) count($low_stock), "{$threshold} or fewer left", 'warning', 'amber'],
];
$actions = '<a href="/admin/products/create" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">'
    . $partial('icon', ['name' => 'plus', 'class' => 'h-4 w-4']) . 'Add product</a>';
?>
<?= $partial('page-header', ['title' => 'Dashboard', 'description' => 'Sales and stock at a glance.', 'actions' => $actions]) ?>

<dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($cards as [$label, $value, $hint, $icon, $tone]): ?>
        <?= $partial('stat-card', ['label' => $label, 'value' => $value, 'hint' => $hint, 'icon' => $icon, 'tone' => $tone]) ?>
    <?php endforeach ?>
</dl>

<div class="mt-6 grid gap-6 xl:grid-cols-5">
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-3" aria-labelledby="trend">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="trend" class="text-base font-semibold text-slate-900">Sales trend</h2>
                <p class="mt-1 text-sm text-slate-500">
                    <span class="font-semibold text-slate-900">$<?= $e($money($trend['revenue'])) ?></span>
                    from <?= $e($trend['count']) ?> <?= $trend['count'] === 1 ? 'purchase' : 'purchases' ?> in the last <?= $e($days) ?> days
                </p>
            </div>
            <nav class="inline-flex rounded-lg bg-slate-100 p-1 text-xs font-medium" aria-label="Sales trend range">
                <?php foreach (App\Services\DashboardService::TREND_DAYS as $option): ?>
                    <a href="/admin?days=<?= $e($option) ?>" class="rounded-md px-2.5 py-1 <?= $option === $days ? 'bg-white text-indigo-700 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:text-slate-900' ?>"<?= $option === $days ? ' aria-current="true"' : '' ?>><?= $e($option) ?> days</a>
                <?php endforeach ?>
            </nav>
        </div>
        <div class="mt-5"><?= $partial('sales-chart', ['days' => $trend['days']]) ?></div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-2" aria-labelledby="warnings">
        <h2 id="warnings" class="text-base font-semibold text-slate-900">Inventory warnings</h2>
        <p class="mt-1 text-sm text-slate-500">Products with <?= $e($threshold) ?> or fewer left.</p>
        <?php if ($low_stock === []): ?>
            <p class="mt-6 rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">Every product has enough stock.</p>
        <?php else: ?>
            <ul class="mt-5 space-y-5">
                <?php foreach ($low_stock as $product): ?>
                    <?php $width = (int) round(min(100, $product->quantityAvailable / $threshold * 100)); ?>
                    <li>
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-900"><?= $e($product->name) ?></p>
                                <p class="text-xs <?= $product->isSoldOut() ? 'text-rose-600' : 'text-amber-600' ?>"><?= $product->isSoldOut() ? 'Sold out' : $e($product->quantityAvailable) . ' units remaining' ?></p>
                            </div>
                            <a href="/admin/products/<?= $e($product->id) ?>/edit" class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500"><?= $partial('icon', ['name' => 'plus', 'class' => 'h-3.5 w-3.5']) ?>Restock</a>
                        </div>
                        <div class="mt-2 h-1.5 rounded-full bg-slate-100" aria-hidden="true">
                            <div class="h-1.5 rounded-full <?= $product->isSoldOut() ? 'bg-rose-500' : 'bg-amber-500' ?>" style="width: <?= $width ?>%"></div>
                        </div>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </section>
</div>

<section class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="latest">
    <div class="flex items-center justify-between">
        <h2 id="latest" class="text-base font-semibold text-slate-900">Latest purchases</h2>
        <a href="/admin/transactions" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">View all →</a>
    </div>
    <div class="mt-4">
        <?php if ($latest === []): ?>
            <p class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">No purchases yet.</p>
        <?php else: ?>
            <?= $partial('transactions-table', ['transactions' => $latest, 'showBuyer' => true, 'framed' => false]) ?>
        <?php endif ?>
    </div>
</section>
