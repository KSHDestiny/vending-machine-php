<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string): string $money
 * @var callable(string, array<string, mixed>): string $partial
 * @var App\Support\Page<App\Models\Transaction> $transactions
 */
?>
<?= $partial('page-header', ['title' => 'Transactions', 'description' => 'Every purchase, newest first.']) ?>
<div class="mt-6">
    <?php if ($transactions->items === []): ?>
        <p class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">No purchases yet.</p>
    <?php else: ?>
        <?= $partial('transactions-table', ['transactions' => $transactions->items, 'showBuyer' => true]) ?>
    <?php endif ?>
</div>
<?= $partial('pagination', ['page' => $transactions, 'url' => static fn (int $page): string => '/admin/transactions?page=' . $page]) ?>
