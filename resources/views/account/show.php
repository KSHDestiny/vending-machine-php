<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string, array<string, mixed>): string $partial
 * @var string $csrf
 * @var App\Models\User $user
 * @var string $name
 * @var array<string, string> $nameErrors
 * @var array<string, string> $passwordErrors
 */
$button = 'rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500';
?>
<?= $partial('page-header', ['title' => 'Account', 'description' => 'Your profile and password.']) ?>

<div class="mt-6 grid gap-6 md:grid-cols-2">
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Profile</h2>
        <dl class="mt-4 space-y-1 text-sm">
            <div class="flex gap-2"><dt class="text-slate-500">Email:</dt><dd class="text-slate-900"><?= $e($user->email) ?></dd></div>
            <div class="flex gap-2"><dt class="text-slate-500">Role:</dt><dd class="text-slate-900"><?= $e(ucfirst($user->role->value)) ?></dd></div>
        </dl>
        <form method="post" action="/account" class="mt-4 space-y-4" data-validate novalidate>
            <?= $csrf ?>
            <input type="hidden" name="_method" value="PUT">
            <?= $partial('field', ['name' => 'name', 'label' => 'Name', 'value' => $name, 'error' => $nameErrors['name'] ?? null, 'attributes' => 'type="text" required maxlength="100" autocomplete="name"']) ?>
            <button type="submit" class="<?= $button ?>">Save name</button>
        </form>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Change password</h2>
        <form method="post" action="/account/password" class="mt-4 space-y-4" data-validate novalidate>
            <?= $csrf ?>
            <input type="hidden" name="_method" value="PUT">
            <?= $partial('field', ['name' => 'current_password', 'label' => 'Current password', 'value' => '', 'error' => $passwordErrors['current_password'] ?? null, 'attributes' => 'type="password" required autocomplete="current-password"']) ?>
            <?= $partial('field', ['name' => 'password', 'label' => 'New password (8+ characters, upper/lowercase, number, symbol)', 'value' => '', 'error' => $passwordErrors['password'] ?? null, 'attributes' => 'type="password" required minlength="8" autocomplete="new-password" data-strong-password data-invalid="New password must be at least 8 characters, include uppercase and lowercase letters, a number and a symbol, and be at most 72 bytes."']) ?>
            <?= $partial('field', ['name' => 'password_confirmation', 'label' => 'Confirm new password', 'value' => '', 'error' => $passwordErrors['password_confirmation'] ?? null, 'attributes' => 'type="password" required autocomplete="new-password" data-same-as="password" data-invalid="Password confirmation does not match."']) ?>
            <button type="submit" class="<?= $button ?>">Change password</button>
        </form>
    </section>
</div>
