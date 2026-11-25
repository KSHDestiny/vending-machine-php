<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string): string $money
 * @var callable(string, array<string, mixed>): string $partial
 * @var App\Support\Page<App\Models\Transaction> $transactions
 * @var array{count: int, revenue: string} $totals
 * @var App\Support\TransactionFilter $filter
 */
$query = $filter->toQuery();
$input = 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30';
$export = '<a href="/admin/transactions/export' . $e($query === [] ? '' : '?' . http_build_query($query)) . '" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50" download>'
    . $partial('icon', ['name' => 'receipt', 'class' => 'h-4 w-4']) . 'Export CSV</a>';
?>
<?= $partial('page-header', ['title' => 'Transactions', 'description' => 'Every purchase, newest first.', 'actions' => $export]) ?>

<form method="get" action="/admin/transactions" class="mt-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5" role="search" aria-label="Filter transactions">
    <label class="block text-xs font-medium text-slate-600">Customer
        <input type="search" name="customer" value="<?= $e($filter->customer) ?>" placeholder="Name or email" maxlength="100" class="mt-1 block w-full <?= $input ?>">
    </label>
    <label class="block text-xs font-medium text-slate-600">Product
        <input type="search" name="product" value="<?= $e($filter->product) ?>" placeholder="Product name" maxlength="100" class="mt-1 block w-full <?= $input ?>">
    </label>
    <label class="block text-xs font-medium text-slate-600">From
        <input type="date" name="from" value="<?= $e($filter->from?->format('Y-m-d')) ?>" class="mt-1 block w-full <?= $input ?>">
    </label>
    <label class="block text-xs font-medium text-slate-600">To
        <input type="date" name="to" value="<?= $e($filter->to?->format('Y-m-d')) ?>" class="mt-1 block w-full <?= $input ?>">
    </label>
    <div class="flex items-end gap-2">
        <button type="submit" class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700"><?= $partial('icon', ['name' => 'search', 'class' => 'h-4 w-4']) ?>Filter</button>
        <?php if ($filter->isActive()): ?><a href="/admin/transactions" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Clear</a><?php endif ?>
    </div>
</form>

<p class="mt-4 text-sm text-slate-600">
    <span class="font-semibold text-slate-900"><?= $e($totals['count']) ?></span> <?= $totals['count'] === 1 ? 'purchase' : 'purchases' ?>,
    <span class="font-semibold text-slate-900">$<?= $e($money($totals['revenue'])) ?></span> in total<?= $filter->isActive() ? ' for these filters' : '' ?>.
</p>

<div class="mt-4">
    <?php if ($transactions->items === []): ?>
        <p class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500"><?= $filter->isActive() ? 'No purchases match these filters.' : 'No purchases yet.' ?></p>
    <?php else: ?>
        <?= $partial('transactions-table', ['transactions' => $transactions->items, 'showBuyer' => true]) ?>
    <?php endif ?>
</div>
<?= $partial('pagination', ['page' => $transactions, 'url' => static fn (int $page): string => '/admin/transactions?' . http_build_query($query + ['page' => $page])]) ?>
