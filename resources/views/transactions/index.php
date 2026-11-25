<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string): string $money
 * @var callable(string, array<string, mixed>): string $partial
 * @var App\Support\Page<App\Models\Transaction> $transactions
 */
?>
<?= $partial('page-header', ['title' => 'My purchases', 'description' => 'Everything you have bought, newest first.']) ?>
<div class="mt-6">
    <?php if ($transactions->items === []): ?>
        <p class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">No purchases yet. <a href="/products" class="font-medium text-indigo-600 hover:underline">Browse products</a></p>
    <?php else: ?>
        <?= $partial('transactions-table', ['transactions' => $transactions->items, 'showBuyer' => false]) ?>
    <?php endif ?>
</div>
<?= $partial('pagination', ['page' => $transactions, 'url' => static fn (int $page): string => '/my-transactions?page=' . $page]) ?>
