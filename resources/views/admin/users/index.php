<?php
/**
 * @var callable(string|int|float|null): string $e
 * @var callable(string, array<string, mixed>): string $partial
 * @var App\Support\Page<App\Models\User> $users
 * @var array{q: string, role: string, status: string} $filters
 */
$url = static fn (array $params): string => '/admin/users?' . http_build_query(array_filter($params + $filters + ['page' => $users->page], static fn ($value): bool => $value !== '' && $value !== 1));
$select = static function (string $name, string $label, array $options, string $current) use ($e, $partial): string {
    $html = '<label class="sr-only" for="filter-' . $e($name) . '">' . $e($label) . '</label>'
        . '<span class="relative inline-flex">'
        . '<select id="filter-' . $e($name) . '" name="' . $e($name) . '" class="appearance-none rounded-lg border border-slate-300 bg-white py-2 pr-9 pl-3 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">';
    foreach ($options as $value => $text) {
        $html .= '<option value="' . $e($value) . '"' . ($value === $current ? ' selected' : '') . '>' . $e($text) . '</option>';
    }

    return $html . '</select>' . $partial('icon', ['name' => 'chevron', 'class' => 'pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-slate-400']) . '</span>';
};
$filtersForm = '<form method="get" action="/admin/users" class="flex flex-wrap gap-2" role="search">'
    . '<input type="search" name="q" value="' . $e($filters['q']) . '" placeholder="Search name or email" maxlength="100" aria-label="Search name or email"'
    . ' class="w-56 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">'
    . $select('role', 'Role', ['' => 'All roles', 'admin' => 'Admins', 'user' => 'Customers'], $filters['role'])
    . $select('status', 'Status', ['' => 'Any status', 'active' => 'Active', 'inactive' => 'Inactive'], $filters['status'])
    . '<button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">'
    . $partial('icon', ['name' => 'search', 'class' => 'h-4 w-4']) . 'Filter</button></form>';
?>
<?= $partial('page-header', ['title' => 'Users', 'description' => 'Customer and admin accounts.', 'actions' => $filtersForm]) ?>

<div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
        <tr>
            <th class="px-4 py-3 font-medium">Name</th>
            <th class="px-4 py-3 font-medium">Email</th>
            <th class="px-4 py-3 font-medium">Role and status</th>
            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
        </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($users->items as $user): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3">
                    <span class="flex items-center gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700"><?= $e(mb_strtoupper(mb_substr($user->name, 0, 1))) ?></span>
                        <span class="font-medium text-slate-900"><?= $e($user->name) ?></span>
                    </span>
                </td>
                <td class="px-4 py-3 text-slate-500"><?= $e($user->email) ?></td>
                <td class="space-x-1 px-4 py-3"><?= $partial('account-badges', ['user' => $user]) ?></td>
                <td class="px-4 py-3 text-right"><a href="/admin/users/<?= $e($user->id) ?>" class="font-medium text-indigo-600 hover:underline">View</a></td>
            </tr>
        <?php endforeach ?>
        <?php if ($users->items === []): ?>
            <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">No accounts match these filters.</td></tr>
        <?php endif ?>
        </tbody>
    </table>
</div>

<?= $partial('pagination', ['page' => $users, 'url' => static fn (int $page): string => $url(['page' => $page])]) ?>
