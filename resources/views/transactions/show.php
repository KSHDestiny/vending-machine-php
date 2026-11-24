<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string): string $money
 * @var ?App\Models\User $currentUser
 * @var App\Models\Transaction $transaction
 */
$rows = [
    'Product' => $transaction->productName,
    'Quantity' => (string) $transaction->quantity,
    'Unit price' => '$' . $money($transaction->unitPrice),
    'Total paid' => '$' . $money($transaction->totalPrice),
    'Date' => $transaction->createdAt,
];
?>
<section class="mx-auto max-w-lg rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-2xl font-semibold text-slate-900">Purchase #<?= $e($transaction->id) ?></h1>
    <dl class="mt-6 divide-y divide-slate-100 text-sm">
        <?php foreach ($rows as $label => $value): ?>
            <div class="flex justify-between py-3">
                <dt class="text-slate-500"><?= $e($label) ?></dt>
                <dd class="font-medium text-slate-900"><?= $e($value) ?></dd>
            </div>
        <?php endforeach ?>
    </dl>
    <div class="mt-6 flex gap-3">
        <a href="/products" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Keep shopping</a>
        <a href="<?= $currentUser?->canManageProducts() ? '/admin/transactions' : '/my-transactions' ?>" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">All purchases</a>
    </div>
</section>
