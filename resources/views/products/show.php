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
?>
<a href="/products" class="text-sm text-indigo-600 hover:underline">← All products</a>
<section class="mt-4 grid gap-8 rounded-lg border border-slate-200 bg-white p-8 shadow-sm md:grid-cols-2">
    <div>
        <h1 class="text-3xl font-semibold text-slate-900"><?= $e($product->name) ?></h1>
        <p class="mt-2 text-3xl font-bold text-indigo-600">$<?= $e($money($product->price)) ?></p>
        <p class="mt-2 <?= $inStock ? 'text-slate-600' : 'text-red-600' ?>"><?= $inStock ? $e($product->quantityAvailable) . ' in stock' : 'Sold out' ?></p>
    </div>

    <div>
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
                <p class="text-sm text-slate-600">Total: <strong data-total>$<?= $e($money($product->price)) ?></strong></p>
                <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Buy now</button>
            </form>
        <?php endif ?>
    </div>
</section>
