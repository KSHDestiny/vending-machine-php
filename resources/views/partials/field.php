<?php
/**
 * A labelled input with its server error beside it, and a slot the browser-side
 * validation (app.js) fills in.
 *
 * @var callable(string|int|float|null): string $e
 * @var string $name
 * @var string $label
 * @var string $value
 * @var ?string $error
 * @var string $attributes extra input attributes, e.g. 'type="number" min="0" required'
 */
$invalid = $error !== null;
?>
<div>
    <label for="<?= $e($name) ?>" class="block text-sm font-medium text-slate-700"><?= $e($label) ?></label>
    <input id="<?= $e($name) ?>" name="<?= $e($name) ?>" value="<?= $e($value) ?>" <?= $attributes ?>
           aria-invalid="<?= $invalid ? 'true' : 'false' ?>" aria-describedby="<?= $e($name) ?>-error"
           class="mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 <?= $invalid ? 'border-red-400' : 'border-slate-300' ?>">
    <p id="<?= $e($name) ?>-error" data-error-for="<?= $e($name) ?>" class="mt-1 text-sm text-red-600"><?= $e($error ?? '') ?></p>
</div>
