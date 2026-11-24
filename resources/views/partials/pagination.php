<?php
/**
 * The pagination bar under every list: "Showing 1–10 of 25", then Previous, page
 * numbers and Next. Shown even for a single page, with the unavailable links greyed out.
 *
 * @var callable(string|int|float|null): string $e
 * @var App\Support\Page<mixed> $page
 * @var callable(int): string $url
 */
$last = $page->lastPage();
$from = $page->total === 0 ? 0 : ($page->page - 1) * $page->perPage + 1;
$to = min($page->page * $page->perPage, $page->total);
$item = 'rounded-md px-3 py-1.5 text-sm';
$link = "{$item} text-slate-600 hover:bg-slate-100";
$disabled = "{$item} cursor-not-allowed text-slate-300";
?>
<nav class="mt-6 flex flex-wrap items-center justify-between gap-3" aria-label="Pages">
    <p class="text-sm text-slate-500">
        Showing <span class="font-medium text-slate-700"><?= $e($from) ?>–<?= $e($to) ?></span> of <span class="font-medium text-slate-700"><?= $e($page->total) ?></span>
    </p>
    <div class="flex flex-wrap items-center gap-1">
        <?php if ($page->page > 1): ?>
            <a href="<?= $e($url($page->page - 1)) ?>" class="<?= $link ?>" rel="prev">← Previous</a>
        <?php else: ?>
            <span class="<?= $disabled ?>" aria-disabled="true">← Previous</span>
        <?php endif ?>
        <?php for ($i = 1; $i <= $last; $i++): ?>
            <?php if ($i === $page->page): ?>
                <span class="<?= $item ?> bg-indigo-600 font-medium text-white" aria-current="page"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= $e($url($i)) ?>" class="<?= $link ?>"><?= $i ?></a>
            <?php endif ?>
        <?php endfor ?>
        <?php if ($page->page < $last): ?>
            <a href="<?= $e($url($page->page + 1)) ?>" class="<?= $link ?>" rel="next">Next →</a>
        <?php else: ?>
            <span class="<?= $disabled ?>" aria-disabled="true">Next →</span>
        <?php endif ?>
    </div>
</nav>
