<?php
/**
 * The add and edit form. Browser checks (required, min, step) mirror the server
 * rules in ProductService::RULES; the server always checks again.
 *
 * @var callable(string|int|float|null): string $e
 * @var callable(string, array<string, mixed>): string $partial
 * @var string $csrf
 * @var string $title
 * @var ?App\Models\Product $product
 * @var array<string, string> $input
 * @var array<string, string> $errors
 */
?>
<a href="/admin/products" class="text-sm text-indigo-600 hover:underline">← Manage products</a>
<section class="mt-4 max-w-lg rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-2xl font-semibold text-slate-900"><?= $e($title) ?></h1>
    <form method="post" action="<?= $product === null ? '/admin/products' : '/admin/products/' . $e($product->id) ?>" class="mt-6 space-y-4" data-validate novalidate>
        <?= $csrf ?>
        <?php if ($product !== null): ?>
            <input type="hidden" name="_method" value="PUT">
        <?php endif ?>
        <?= $partial('field', ['name' => 'name', 'label' => 'Name', 'value' => $input['name'] ?? '', 'error' => $errors['name'] ?? null, 'attributes' => 'type="text" required maxlength="100"']) ?>
        <?= $partial('field', ['name' => 'price', 'label' => 'Price (USD)', 'value' => $input['price'] ?? '', 'error' => $errors['price'] ?? null, 'attributes' => 'type="number" required min="0.001" max="9999999.999" step="0.001" inputmode="decimal" data-invalid="Price must be greater than 0, with up to 3 decimals."']) ?>
        <?= $partial('field', ['name' => 'quantity_available', 'label' => 'Quantity available', 'value' => $input['quantity_available'] ?? '', 'error' => $errors['quantity_available'] ?? null, 'attributes' => 'type="number" required min="0" max="999999999" step="1" inputmode="numeric" data-invalid="Quantity available must be a whole number of 0 or more."']) ?>
        <div class="flex gap-3">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"><?= $product === null ? 'Add product' : 'Save changes' ?></button>
            <a href="/admin/products" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
        </div>
    </form>
</section>
