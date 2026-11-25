<?php
/**
 * A list of purchases; each one links to the purchase. On phones each purchase is a
 * stacked card; from the sm breakpoint up it is a table row. $framed draws its own card
 * border (default); pass false when the list already sits inside a card.
 *
 * @var callable(string|int|float|null): string $e
 * @var callable(string): string $money
 * @var list<App\Models\Transaction> $transactions
 * @var bool $showBuyer
 * @var bool|null $framed
 */
$frame = ($framed ?? true) ? 'rounded-xl border border-slate-200 bg-white shadow-sm' : '';
?>
<div class="<?= $frame ?>">
    <ul class="divide-y divide-slate-100 sm:hidden">
        <?php foreach ($transactions as $transaction): ?>
            <li>
                <a href="/transactions/<?= $e($transaction->id) ?>" class="block px-4 py-3 hover:bg-slate-50">
                    <span class="flex items-baseline justify-between gap-3">
                        <span class="font-medium text-slate-900"><?= $e($transaction->productName) ?></span>
                        <span class="font-semibold text-slate-900">$<?= $e($money($transaction->totalPrice)) ?></span>
                    </span>
                    <span class="mt-0.5 flex items-baseline justify-between gap-3 text-sm text-slate-500">
                        <span><?= $e($transaction->quantity) ?> × $<?= $e($money($transaction->unitPrice)) ?></span>
                        <span class="text-xs"><?= $e($transaction->createdAt) ?></span>
                    </span>
                    <?php if ($showBuyer): ?>
                        <span class="mt-1 block truncate text-xs text-slate-500"><?= $e($transaction->userName) ?> · <?= $e($transaction->userEmail) ?></span>
                    <?php endif ?>
                </a>
            </li>
        <?php endforeach ?>
    </ul>
    <div class="hidden overflow-x-auto sm:block">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
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
                <tr class="hover:bg-slate-50">
                    <td class="whitespace-nowrap px-4 py-3 text-slate-500"><a href="/transactions/<?= $e($transaction->id) ?>" class="hover:text-indigo-600 hover:underline"><?= $e($transaction->createdAt) ?></a></td>
                    <?php if ($showBuyer): ?><td class="px-4 py-3"><span class="font-medium text-slate-900"><?= $e($transaction->userName) ?></span><span class="ml-1 text-slate-400"><?= $e($transaction->userEmail) ?></span></td><?php endif ?>
                    <td class="px-4 py-3 font-medium text-slate-900"><?= $e($transaction->productName) ?></td>
                    <td class="px-4 py-3 text-right"><?= $e($transaction->quantity) ?></td>
                    <td class="px-4 py-3 text-right">$<?= $e($money($transaction->unitPrice)) ?></td>
                    <td class="px-4 py-3 text-right font-semibold text-slate-900">$<?= $e($money($transaction->totalPrice)) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
