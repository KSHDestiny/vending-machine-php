<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string): string $money
 * @var callable(string, array<string, mixed>): string $partial
 * @var App\Support\Page<App\Models\Transaction> $transactions
 */
?>
<h1 class="text-2xl font-semibold text-slate-900">All transactions</h1>
<div class="mt-6">
    <?php if ($transactions->items === []): ?>
        <p class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500">No purchases yet.</p>
    <?php else: ?>
        <?= $partial('transactions-table', ['e' => $e, 'money' => $money, 'transactions' => $transactions->items, 'showBuyer' => true]) ?>
    <?php endif ?>
</div>
<?= $partial('pagination', ['e' => $e, 'page' => $transactions, 'url' => static fn (int $page): string => '/admin/transactions?page=' . $page]) ?>
