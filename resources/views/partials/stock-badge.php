<?php
/**
 * The stock level as a coloured badge: sold out, low (5 or fewer) or in stock.
 *
 * @var callable(string|int|float|null): string $e
 * @var App\Models\Product $product
 */
[$label, $style] = match (true) {
    $product->isSoldOut() => ['Sold out', 'bg-rose-50 text-rose-700 ring-rose-600/20'],
    $product->quantityAvailable <= App\Services\DashboardService::LOW_STOCK => ["{$product->quantityAvailable} left", 'bg-amber-50 text-amber-700 ring-amber-600/20'],
    default => ["{$product->quantityAvailable} in stock", 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
};
?>
<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset <?= $style ?>"><?= $e($label) ?></span>
