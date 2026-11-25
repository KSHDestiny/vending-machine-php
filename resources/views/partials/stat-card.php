<?php
/**
 * A KPI card: label with an icon, a large value and a short line of context.
 *
 * @var callable(string|int|float|null): string $e
 * @var callable(string, array<string, mixed>): string $partial
 * @var string $label
 * @var string $value
 * @var string $hint
 * @var string $icon
 * @var 'indigo'|'emerald'|'amber'|'sky' $tone
 */
$tones = [
    'indigo' => 'bg-indigo-50 text-indigo-600',
    'emerald' => 'bg-emerald-50 text-emerald-600',
    'amber' => 'bg-amber-50 text-amber-600',
    'sky' => 'bg-sky-50 text-sky-600',
];
?>
<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <dt class="text-sm font-medium text-slate-500"><?= $e($label) ?></dt>
        <span class="flex h-8 w-8 items-center justify-center rounded-lg <?= $tones[$tone] ?>"><?= $partial('icon', ['name' => $icon, 'class' => 'h-5 w-5']) ?></span>
    </div>
    <dd class="mt-3 text-3xl font-semibold tracking-tight text-slate-900"><?= $e($value) ?></dd>
    <p class="mt-1 text-xs text-slate-500"><?= $e($hint) ?></p>
</div>
