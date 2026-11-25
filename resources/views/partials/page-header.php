<?php
/**
 * The heading at the top of a page: title, an optional description, and optional
 * actions on the right (already-escaped HTML, e.g. a search form or buttons).
 *
 * @var callable(string|int|float|null): string $e
 * @var string $title
 * @var string|null $description
 * @var string|null $actions
 */
?>
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900"><?= $e($title) ?></h1>
        <?php if (($description ?? null) !== null): ?>
            <p class="mt-1 text-sm text-slate-500"><?= $e($description) ?></p>
        <?php endif ?>
    </div>
    <?php if (($actions ?? null) !== null): ?>
        <div class="flex flex-wrap items-center gap-2"><?= $actions ?></div>
    <?php endif ?>
</div>
