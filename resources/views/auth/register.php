<?php
/**
 * @var callable(string, array<string, mixed>): string $partial
 * @var string $csrf
 * @var array{name: string, email: string} $input
 * @var array<string, string> $errors
 */
?>
<section class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Create an account</h1>
    <form method="post" action="/register" class="mt-6 space-y-4" data-validate novalidate>
        <?= $csrf ?>
        <?= $partial('field', ['name' => 'name', 'label' => 'Name', 'value' => $input['name'], 'error' => $errors['name'] ?? null, 'attributes' => 'type="text" required maxlength="100" autocomplete="name" autofocus']) ?>
        <?= $partial('field', ['name' => 'email', 'label' => 'Email', 'value' => $input['email'], 'error' => $errors['email'] ?? null, 'attributes' => 'type="email" required maxlength="255" autocomplete="email" data-invalid="Email must be a valid email address."']) ?>
        <?= $partial('field', ['name' => 'password', 'label' => 'Password (8+ characters, upper/lowercase, number, symbol)', 'value' => '', 'error' => $errors['password'] ?? null, 'attributes' => 'type="password" required minlength="8" autocomplete="new-password" data-strong-password data-invalid="Password must be at least 8 characters, include uppercase and lowercase letters, a number and a symbol, and be at most 72 bytes."']) ?>
        <?= $partial('field', ['name' => 'password_confirmation', 'label' => 'Confirm password', 'value' => '', 'error' => $errors['password_confirmation'] ?? null, 'attributes' => 'type="password" required autocomplete="new-password" data-same-as="password" data-invalid="Password confirmation does not match."']) ?>
        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Register</button>
    </form>
    <p class="mt-6 text-sm text-slate-600">Already have an account? <a href="/login" class="font-medium text-indigo-600 hover:underline">Log in</a></p>
</section>
