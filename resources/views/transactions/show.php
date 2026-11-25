<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string): string $money
 * @var ?App\Models\User $currentUser
 * @var App\Models\Transaction $transaction
 */
$isAdmin = $currentUser?->canManageProducts() ?? false;
$rows = [
    'Product' => $transaction->productName,
    'Quantity' => (string) $transaction->quantity,
    'Unit price' => '$' . $money($transaction->unitPrice),
    'Total paid' => '$' . $money($transaction->totalPrice),
    'Date' => $transaction->createdAt,
];
?>
<section class="mx-auto max-w-lg rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <p class="text-sm font-medium text-indigo-600"><?= $isAdmin ? 'Purchase details' : 'Purchase confirmation' ?></p>
    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Purchase #<?= $e($transaction->id) ?></h1>
    <dl class="mt-6 divide-y divide-slate-100 text-sm">
        <?php if ($isAdmin): ?>
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-slate-500">Buyer</dt>
                <dd class="text-right"><a href="/admin/users/<?= $e($transaction->userId) ?>" class="font-medium text-indigo-600 hover:underline"><?= $e($transaction->userName) ?></a><span class="block text-xs text-slate-500"><?= $e($transaction->userEmail) ?></span></dd>
            </div>
        <?php endif ?>
        <?php foreach ($rows as $label => $value): ?>
            <div class="flex justify-between py-3">
                <dt class="text-slate-500"><?= $e($label) ?></dt>
                <dd class="font-medium text-slate-900"><?= $e($value) ?></dd>
            </div>
        <?php endforeach ?>
    </dl>
    <div class="mt-6 flex flex-wrap gap-3">
        <?php if ($isAdmin): ?>
            <a href="/admin/transactions" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">All transactions</a>
            <a href="/admin/products/<?= $e($transaction->productId) ?>/inventory" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"><?= $e($transaction->productName) ?> stock</a>
        <?php else: ?>
            <a href="/products" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Keep shopping</a>
            <a href="/my-transactions" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">All purchases</a>
        <?php endif ?>
    </div>
</section>
