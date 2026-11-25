<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string): string $money
 * @var callable(string, array<string, mixed>): string $partial
 * @var string $csrf
 * @var ?App\Models\User $currentUser
 * @var App\Models\Product $product
 * @var string $quantity
 * @var array<string, string> $errors
 */
$inStock = !$product->isSoldOut();
// The total for the entered quantity when it can be bought, so it is right without JavaScript too.
$total = $product->price;
if (ctype_digit($quantity) && (int) $quantity >= 1 && (int) $quantity <= $product->quantityAvailable) {
    try {
        $total = $product->totalFor((int) $quantity);
    } catch (App\Exceptions\ValidationException) {
        // Too large to buy at once; the quantity error already says so.
    }
}
?>
<a href="/products" class="text-sm font-medium text-slate-500 hover:text-slate-900">← All products</a>
<section class="mt-4 grid gap-8 rounded-xl border border-slate-200 bg-white p-8 shadow-sm md:grid-cols-2">
    <div>
        <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"><?= $partial('icon', ['name' => 'bag', 'class' => 'h-7 w-7']) ?></span>
        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900"><?= $e($product->name) ?></h1>
        <p class="mt-2 text-3xl font-semibold text-indigo-600">$<?= $e($money($product->price)) ?></p>
        <div class="mt-3"><?= $partial('stock-badge', ['product' => $product]) ?></div>
    </div>

    <div class="md:border-l md:border-slate-100 md:pl-8">
        <?php if ($currentUser === null || !$currentUser->canPurchase()): ?>
            <p class="text-slate-600">Admins manage stock; customers buy. <a href="/admin/products/<?= $e($product->id) ?>/edit" class="font-medium text-indigo-600 hover:underline">Edit this product</a></p>
        <?php elseif (!$inStock): ?>
            <p class="text-slate-600">This product is sold out.</p>
        <?php else: ?>
            <form method="post" action="/products/<?= $e($product->id) ?>/purchase" class="space-y-4" data-validate novalidate>
                <?= $csrf ?>
                <?= $partial('field', [
                    'name' => 'quantity',
                    'label' => 'Quantity',
                    'value' => $quantity,
                    'error' => $errors['quantity'] ?? null,
                    'attributes' => 'type="number" required min="1" max="' . $e($product->quantityAvailable) . '" step="1" inputmode="numeric" data-invalid="Quantity must be a whole number of 1 or more." data-max-message="Only ' . $e($product->quantityAvailable) . ' left in stock." data-price="' . $e($product->price) . '"',
                ]) ?>
                <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3 text-sm">
                    <span class="text-slate-600">Total</span>
                    <strong class="text-lg text-slate-900" data-total>$<?= $e($money($total)) ?></strong>
                </div>
                <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Buy now</button>
            </form>
        <?php endif ?>
    </div>
</section>
