<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string): string $money
 * @var callable(string, array<string, mixed>): string $partial
 * @var string $csrf
 * @var App\Models\User $user
 * @var array{count: int, items_sold: int, revenue: string} $summary
 * @var list<App\Models\Transaction> $recent
 * @var array<string, string> $errors
 * @var bool $isSelf
 * @var array{role: ?string, status: ?string} $restrictions why a role change or deactivation is not allowed, or null
 */
$error = static fn (string $field) => isset($errors[$field])
    ? '<p class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700" role="alert">' . $e($errors[$field]) . '</p>'
    : '';
$button = 'rounded-lg px-4 py-2 text-sm font-semibold shadow-sm disabled:cursor-not-allowed disabled:opacity-50';
// The reason a control is disabled, unless the same reason is already shown as an error.
$note = static fn (string $field) => ($restrictions[$field] ?? null) !== null && !isset($errors[$field])
    ? '<p class="mt-2 text-xs text-slate-500">' . $e($restrictions[$field]) . '</p>'
    : '';
?>
<a href="/admin/users" class="text-sm font-medium text-slate-500 hover:text-slate-900">← Users</a>

<section class="mt-4 flex flex-wrap items-center gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-indigo-100 text-xl font-semibold text-indigo-700"><?= $e(mb_strtoupper(mb_substr($user->name, 0, 1))) ?></span>
    <div class="min-w-0 flex-1">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900"><?= $e($user->name) ?><?= $isSelf ? ' <span class="text-sm font-normal text-slate-500">(you)</span>' : '' ?></h1>
        <p class="text-sm text-slate-500"><?= $e($user->email) ?></p>
    </div>
    <div class="space-x-1"><?= $partial('account-badges', ['user' => $user]) ?></div>
</section>

<dl class="mt-6 grid gap-4 sm:grid-cols-3">
    <?= $partial('stat-card', ['label' => 'Purchases', 'value' => (string) $summary['count'], 'hint' => 'All time', 'icon' => 'cart', 'tone' => 'indigo']) ?>
    <?= $partial('stat-card', ['label' => 'Items bought', 'value' => (string) $summary['items_sold'], 'hint' => 'All time', 'icon' => 'box', 'tone' => 'sky']) ?>
    <?= $partial('stat-card', ['label' => 'Total spent', 'value' => '$' . $money($summary['revenue']), 'hint' => 'At the prices paid', 'icon' => 'money', 'tone' => 'emerald']) ?>
</dl>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="role-heading">
        <h2 id="role-heading" class="text-base font-semibold text-slate-900">Role</h2>
        <p class="mt-1 text-sm text-slate-500">Choose what this account can do.</p>
        <form method="post" action="/admin/users/<?= $e($user->id) ?>/role" class="mt-4" data-require-change>
            <?= $csrf ?>
            <input type="hidden" name="_method" value="PATCH">
            <fieldset<?= $restrictions['role'] !== null ? ' disabled' : '' ?>>
                <legend class="sr-only">Role</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <?php foreach ([[App\Enums\Role::Admin, 'dashboard', 'Manages products and stock, sees every purchase.'], [App\Enums\Role::User, 'bag', 'Buys products and sees their own purchases.']] as [$role, $icon, $description]): ?>
                        <?php $current = $role === $user->role; ?>
                        <label class="relative flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-4 hover:border-slate-300 has-checked:border-indigo-600 has-checked:bg-indigo-50/60 has-checked:ring-1 has-checked:ring-indigo-600 has-focus-visible:ring-2 has-focus-visible:ring-indigo-500 has-disabled:cursor-not-allowed has-disabled:opacity-60">
                            <input type="radio" name="role" value="<?= $e($role->value) ?>" class="peer sr-only"<?= $current ? ' checked' : '' ?>>
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 peer-checked:bg-indigo-600 peer-checked:text-white"><?= $partial('icon', ['name' => $icon, 'class' => 'h-5 w-5']) ?></span>
                            <span class="min-w-0">
                                <span class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                    <?= $e($role->label()) ?>
                                    <?php if ($current): ?><span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-500">Current</span><?php endif ?>
                                </span>
                                <span class="mt-0.5 block text-xs text-slate-500"><?= $e($description) ?></span>
                            </span>
                        </label>
                    <?php endforeach ?>
                </div>
                <button type="submit" class="<?= $button ?> mt-4 bg-indigo-600 text-white hover:bg-indigo-500">Save role</button>
                <p class="mt-2 hidden text-xs text-slate-500" data-change-hint>Choose a different role first.</p>
            </fieldset>
        </form>
        <?= $error('role') ?>
        <?= $note('role') ?>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="status-heading">
        <h2 id="status-heading" class="text-base font-semibold text-slate-900">Access</h2>
        <p class="mt-1 text-sm text-slate-500">Deactivated accounts cannot sign in and are signed out at once. Their purchases are kept.</p>
        <?php $deactivate = $user->isActive(); ?>
        <form method="post" action="/admin/users/<?= $e($user->id) ?>/status" class="mt-4"
              <?= $deactivate ? 'data-confirm="Deactivate ' . $e($user->name) . '? They will be signed out and cannot sign in."' : '' ?>>
            <?= $csrf ?>
            <input type="hidden" name="_method" value="PATCH">
            <input type="hidden" name="status" value="<?= $deactivate ? 'inactive' : 'active' ?>">
            <button type="submit" class="<?= $button ?> <?= $deactivate ? 'bg-rose-600 text-white hover:bg-rose-500' : 'bg-emerald-600 text-white hover:bg-emerald-500' ?>"<?= $restrictions['status'] !== null ? ' disabled' : '' ?>><?= $deactivate ? 'Deactivate account' : 'Reactivate account' ?></button>
        </form>
        <?= $error('status') ?>
        <?= $note('status') ?>
    </section>
</div>

<section class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="recent-heading">
    <h2 id="recent-heading" class="text-base font-semibold text-slate-900">Latest purchases</h2>
    <div class="mt-4">
        <?php if ($recent === []): ?>
            <p class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">No purchases yet.</p>
        <?php else: ?>
            <?= $partial('transactions-table', ['transactions' => $recent, 'showBuyer' => false, 'framed' => false]) ?>
        <?php endif ?>
    </div>
</section>
