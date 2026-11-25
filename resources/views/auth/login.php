<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string, array<string, mixed>): string $partial
 * @var string $csrf
 * @var string $email
 * @var array<string, string> $errors
 * @var int|null $retryAfter seconds the server refuses logins for, shown as a countdown by app.js
 */
?>
<section class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Log in</h1>
    <?php if (($retryAfter ?? 0) > 0): ?>
        <?php // One message; app.js counts the seconds down in place and says when to try again. ?>
        <p class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700" role="alert" aria-live="polite" data-login-cooldown>Too many failed logins. Try again in <span data-login-seconds><?= $e($retryAfter) ?></span> <?= $retryAfter === 1 ? 'second' : 'seconds' ?>.</p>
    <?php elseif (isset($errors['form'])): ?>
        <p class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"><?= $e($errors['form']) ?></p>
    <?php endif ?>
    <form method="post" action="/login" class="mt-6 space-y-4" data-validate novalidate data-login-limit<?= ($retryAfter ?? 0) > 0 ? ' data-login-retry-after="' . $e($retryAfter) . '"' : '' ?>>
        <?= $csrf ?>
        <?= $partial('field', ['name' => 'email', 'label' => 'Email', 'value' => $email, 'error' => $errors['email'] ?? null, 'attributes' => 'type="email" required autocomplete="email" autofocus data-invalid="Email must be a valid email address."']) ?>
        <?= $partial('field', ['name' => 'password', 'label' => 'Password', 'value' => '', 'error' => $errors['password'] ?? null, 'attributes' => 'type="password" required autocomplete="current-password"']) ?>
        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">Log in</button>
    </form>
    <p class="mt-6 text-sm text-slate-600">No account? <a href="/register" class="font-medium text-indigo-600 hover:underline">Register</a></p>
</section>
