<?php
/**
 * A user's role and status as two small badges.
 *
 * @var callable(string|int|float|null): string $e
 * @var App\Models\User $user
 */
$roleStyle = $user->isAdmin() ? 'bg-indigo-50 text-indigo-700 ring-indigo-600/20' : 'bg-slate-50 text-slate-700 ring-slate-500/20';
$statusStyle = $user->isActive() ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-rose-50 text-rose-700 ring-rose-600/20';
?>
<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset <?= $roleStyle ?>"><?= $e($user->role->label()) ?></span>
<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset <?= $statusStyle ?>"><?= $e($user->status->label()) ?></span>
