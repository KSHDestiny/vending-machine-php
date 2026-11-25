<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string, array<string, mixed>): string $partial
 * @var string $csrf
 * @var App\Models\Product $product
 * @var App\Support\Page<App\Models\InventoryMovement> $movements
 * @var string $type
 * @var array{quantity: string, reason: string} $input
 * @var array<string, string> $errors
 */
$filters = ['' => 'All changes'] + array_combine(
    array_map(static fn (App\Enums\MovementType $case): string => $case->value, App\Enums\MovementType::cases()),
    array_map(static fn (App\Enums\MovementType $case): string => $case->label(), App\Enums\MovementType::cases()),
);
$badges = [
    'INITIAL_STOCK' => 'bg-slate-50 text-slate-700 ring-slate-500/20',
    'RESTOCK' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    'PURCHASE' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
    'ADJUSTMENT' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
];
$url = static fn (array $params): string => "/admin/products/{$product->id}/inventory?" . http_build_query(array_filter($params + ['type' => $type], static fn ($value): bool => $value !== '' && $value !== 1));
?>
<a href="/admin/products" class="text-sm font-medium text-slate-500 hover:text-slate-900">← Products</a>
<div class="mt-4">
    <?= $partial('page-header', ['title' => "{$product->name} stock", 'description' => 'Every change to this product\'s stock, newest first.']) ?>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="restock-heading">
        <p class="text-sm font-medium text-slate-500">In stock now</p>
        <p class="mt-1 text-3xl font-semibold tracking-tight text-slate-900"><?= $e($product->quantityAvailable) ?></p>
        <div class="mt-2"><?= $partial('stock-badge', ['product' => $product]) ?></div>

        <h2 id="restock-heading" class="mt-6 text-base font-semibold text-slate-900">Restock</h2>
        <form method="post" action="/admin/products/<?= $e($product->id) ?>/restock" class="mt-3 space-y-4" data-validate novalidate>
            <?= $csrf ?>
            <?= $partial('field', ['name' => 'quantity', 'label' => 'Quantity to add', 'value' => $input['quantity'], 'error' => $errors['quantity'] ?? null, 'attributes' => 'type="number" required min="1" max="999999999" step="1" inputmode="numeric" data-invalid="Quantity must be a whole number of 1 or more."']) ?>
            <?= $partial('field', ['name' => 'reason', 'label' => 'Reason (optional)', 'value' => $input['reason'], 'error' => $errors['reason'] ?? null, 'attributes' => 'type="text" maxlength="255" placeholder="e.g. Delivery from supplier"']) ?>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"><?= $partial('icon', ['name' => 'plus', 'class' => 'h-4 w-4']) ?>Add stock</button>
        </form>
        <p class="mt-4 text-xs text-slate-500">To correct the count instead, change "Quantity available" on the <a href="/admin/products/<?= $e($product->id) ?>/edit" class="font-medium text-indigo-600 hover:underline">product form</a>; it is recorded as an adjustment.</p>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2" aria-labelledby="history-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="history-heading" class="text-base font-semibold text-slate-900">History</h2>
            <nav class="inline-flex flex-wrap rounded-lg bg-slate-100 p-1 text-xs font-medium" aria-label="Filter by type">
                <?php foreach ($filters as $value => $label): ?>
                    <?php $active = $value === $type || ($value === '' && !isset($filters[$type])); ?>
                    <a href="<?= $e($url(['type' => $value, 'page' => 1])) ?>" class="rounded-md px-2.5 py-1 <?= $active ? 'bg-white text-indigo-700 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:text-slate-900' ?>"<?= $active ? ' aria-current="true"' : '' ?>><?= $e($label) ?></a>
                <?php endforeach ?>
            </nav>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Date</th>
                    <th class="px-4 py-3 font-medium">Type</th>
                    <th class="px-4 py-3 text-right font-medium">Change</th>
                    <th class="px-4 py-3 text-right font-medium">Stock</th>
                    <th class="px-4 py-3 font-medium">By and why</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($movements->items as $movement): ?>
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500"><?= $e($movement->createdAt) ?></td>
                        <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset <?= $badges[$movement->type->value] ?>"><?= $e($movement->type->label()) ?></span></td>
                        <td class="px-4 py-3 text-right font-semibold <?= $movement->quantityChange < 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= $e(($movement->quantityChange > 0 ? '+' : '') . $movement->quantityChange) ?></td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-slate-500"><?= $e($movement->quantityBefore) ?> → <span class="font-medium text-slate-900"><?= $e($movement->quantityAfter) ?></span></td>
                        <td class="px-4 py-3 text-slate-600">
                            <?= $e($movement->userName ?? 'System') ?>
                            <?php if ($movement->transactionId !== null): ?>· <a href="/transactions/<?= $e($movement->transactionId) ?>" class="text-indigo-600 hover:underline">purchase #<?= $e($movement->transactionId) ?></a><?php endif ?>
                            <?php if ($movement->reason !== null): ?><span class="block text-xs text-slate-400"><?= $e($movement->reason) ?></span><?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if ($movements->items === []): ?>
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No stock changes of this type yet.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
        <?= $partial('pagination', ['page' => $movements, 'url' => static fn (int $page): string => $url(['page' => $page])]) ?>
    </section>
</div>
