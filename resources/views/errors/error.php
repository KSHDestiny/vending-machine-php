<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var string $title
 * @var string $message
 * @var int $status
 */
?>
<section class="mx-auto max-w-lg rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
    <p class="text-sm font-semibold text-indigo-600"><?= $e($status) ?></p>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900"><?= $e($title) ?></h1>
    <p class="mt-3 text-slate-600"><?= $e($message) ?></p>
    <a href="/products" class="mt-6 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Back to products</a>
</section>
