<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string): string $money
 * @var list<App\Models\Transaction> $transactions
 * @var bool $showBuyer
 */
?>
<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-600">
        <tr>
            <th class="px-4 py-3 font-medium">Date</th>
            <?php if ($showBuyer): ?><th class="px-4 py-3 font-medium">Buyer</th><?php endif ?>
            <th class="px-4 py-3 font-medium">Product</th>
            <th class="px-4 py-3 text-right font-medium">Quantity</th>
            <th class="px-4 py-3 text-right font-medium">Unit price</th>
            <th class="px-4 py-3 text-right font-medium">Total</th>
        </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($transactions as $transaction): ?>
            <tr>
                <td class="px-4 py-3 text-slate-500"><a href="/transactions/<?= $e($transaction->id) ?>" class="hover:underline"><?= $e($transaction->createdAt) ?></a></td>
                <?php if ($showBuyer): ?><td class="px-4 py-3"><?= $e($transaction->userName) ?> <span class="text-slate-400"><?= $e($transaction->userEmail) ?></span></td><?php endif ?>
                <td class="px-4 py-3 font-medium text-slate-900"><?= $e($transaction->productName) ?></td>
                <td class="px-4 py-3 text-right"><?= $e($transaction->quantity) ?></td>
                <td class="px-4 py-3 text-right">$<?= $e($money($transaction->unitPrice)) ?></td>
                <td class="px-4 py-3 text-right font-medium">$<?= $e($money($transaction->totalPrice)) ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
