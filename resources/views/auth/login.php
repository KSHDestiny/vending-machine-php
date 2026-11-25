<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string, array<string, mixed>): string $partial
 * @var string $csrf
 * @var string $email
 * @var array<string, string> $errors
 */
?>
<section class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Log in</h1>
    <?php if (isset($errors['form'])): ?>
        <p class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"><?= $e($errors['form']) ?></p>
    <?php endif ?>
    <form method="post" action="/login" class="mt-6 space-y-4" data-validate novalidate>
        <?= $csrf ?>
        <?= $partial('field', ['name' => 'email', 'label' => 'Email', 'value' => $email, 'error' => $errors['email'] ?? null, 'attributes' => 'type="email" required autocomplete="email" autofocus data-invalid="Email must be a valid email address."']) ?>
        <?= $partial('field', ['name' => 'password', 'label' => 'Password', 'value' => '', 'error' => $errors['password'] ?? null, 'attributes' => 'type="password" required autocomplete="current-password"']) ?>
        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Log in</button>
    </form>
    <p class="mt-6 text-sm text-slate-600">No account? <a href="/register" class="font-medium text-indigo-600 hover:underline">Register</a></p>
</section>
