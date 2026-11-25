<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\TransactionManager;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Page;
use LogicException;

/*
 * Admin user management: list and view accounts, change roles, activate and
 * deactivate. Admins cannot change themselves, the primary admin (admin@example.com)
 * always stays an active admin, and the last active admin can be neither demoted nor
 * deactivated. restrictions() gives pages the same reasons, so they can disable those controls. Each change runs in one transaction with the
 * active admin rows locked, so two admins acting at once cannot remove the last one.
 * Purchases are never touched.
 */
final class UserManagementService
{
    /** @var int Accounts per page. */
    public const PER_PAGE = 10;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly TransactionRepositoryInterface $transactions,
        private readonly TransactionManager $database,
    ) {
    }

    /**
     * One page of accounts; unknown role or status filters are ignored.
     * @return Page<User>
     */
    public function list(int $page, string $search, string $role, string $status): Page
    {
        return $this->users->paginateForAdmin(max(1, $page), self::PER_PAGE, mb_substr(trim($search), 0, 100), Role::tryFrom($role), UserStatus::tryFrom($status));
    }

    /**
     * The account with its purchase totals, latest purchases and what $actor may not change on it; NotFoundException if missing.
     * @return array{user: User, summary: array{count: int, items_sold: int, revenue: string}, recent: list<Transaction>, restrictions: array{role: ?string, status: ?string}}
     */
    public function profile(int $id, User $actor): array
    {
        $user = $this->users->find($id) ?? throw new NotFoundException('User not found.');

        return [
            'user' => $user,
            'summary' => $this->transactions->summary($user->id),
            'recent' => $this->transactions->paginateForUser($user->id, 1, 5)->items,
            'restrictions' => $this->restrictions($actor, $user),
        ];
    }

    /**
     * Why $actor may not change the user's role or deactivate them (null when allowed), so pages can
     * disable those controls and say why. The same rules are enforced again, under lock, when saving.
     * @return array{role: ?string, status: ?string}
     */
    public function restrictions(User $actor, User $user): array
    {
        $activeAdmins = $this->users->countActiveAdmins();

        return [
            'role' => self::roleRestriction($actor, $user, $activeAdmins),
            'status' => $user->isActive() ? self::deactivationRestriction($actor, $user, $activeAdmins) : null,
        ];
    }

    /**
     * Gives the account the role ('admin' or 'user'); ValidationException if not allowed.
     * 'changed' is false when the account already had that role.
     * @return array{user: User, changed: bool}
     */
    public function changeRole(User $actor, int $id, string $role): array
    {
        $newRole = Role::tryFrom($role) ?? throw new ValidationException(['role' => 'Choose Admin or Customer.']);

        return $this->change($id, function (User $user, int $activeAdmins) use ($actor, $newRole): bool {
            if ($user->id !== $actor->id && $user->role === $newRole) {
                return false;
            }
            $reason = self::roleRestriction($actor, $user, $activeAdmins);
            if ($reason !== null) {
                throw new ValidationException(['role' => $reason]);
            }
            $this->users->updateRole($user->id, $newRole);

            return true;
        });
    }

    /**
     * Activates or deactivates the account ('active' or 'inactive'); ValidationException if not allowed.
     * 'changed' is false when the account already had that status.
     * @return array{user: User, changed: bool}
     */
    public function changeStatus(User $actor, int $id, string $status): array
    {
        $newStatus = UserStatus::tryFrom($status) ?? throw new ValidationException(['status' => 'Choose Active or Inactive.']);

        return $this->change($id, function (User $user, int $activeAdmins) use ($actor, $newStatus): bool {
            if ($user->id === $actor->id) {
                throw new ValidationException(['status' => 'You cannot deactivate your own account.']);
            }
            if ($user->status === $newStatus) {
                return false;
            }
            $reason = $newStatus === UserStatus::Inactive ? self::deactivationRestriction($actor, $user, $activeAdmins) : null;
            if ($reason !== null) {
                throw new ValidationException(['status' => $reason]);
            }
            $this->users->updateStatus($user->id, $newStatus);

            return true;
        });
    }

    /**
     * Runs $apply (which returns whether it changed anything) for the account inside a transaction
     * after locking the active admins, then returns the account as saved.
     * @param callable(User, int): bool $apply receives the user and the number of active admins
     * @return array{user: User, changed: bool}
     */
    private function change(int $id, callable $apply): array
    {
        return $this->database->transaction(function () use ($id, $apply): array {
            $activeAdmins = count($this->users->lockActiveAdminIds());
            $user = $this->users->find($id) ?? throw new NotFoundException('User not found.');
            if (!$apply($user, $activeAdmins)) {
                return ['user' => $user, 'changed' => false];
            }

            // Reload to return the account as saved; it cannot vanish inside this transaction.
            return ['user' => $this->users->find($user->id) ?? throw new LogicException("User {$user->id} disappeared during an update"), 'changed' => true];
        });
    }

    /**
     * Why the user's role cannot change (there are only two roles, so for an admin that means demotion), or null.
     * @return string|null
     */
    private static function roleRestriction(User $actor, User $user, int $activeAdmins): ?string
    {
        return match (true) {
            $user->id === $actor->id => 'You cannot change your own role.',
            !$user->isAdmin() => null,
            $user->isPrimaryAdmin() => User::PRIMARY_ADMIN_EMAIL . ' is the primary admin and cannot be changed to Customer.',
            $user->isActive() && $activeAdmins <= 1 => 'The last active admin cannot be demoted.',
            default => null,
        };
    }

    /**
     * Why the user cannot be deactivated, or null.
     * @return string|null
     */
    private static function deactivationRestriction(User $actor, User $user, int $activeAdmins): ?string
    {
        return match (true) {
            $user->id === $actor->id => 'You cannot deactivate your own account.',
            $user->isPrimaryAdmin() => User::PRIMARY_ADMIN_EMAIL . ' is the primary admin and cannot be deactivated.',
            $user->isAdmin() && $activeAdmins <= 1 => 'The last active admin cannot be deactivated.',
            default => null,
        };
    }
}
