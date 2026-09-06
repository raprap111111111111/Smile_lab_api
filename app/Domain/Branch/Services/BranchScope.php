<?php

namespace App\Domain\Branch\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which branches a user is allowed to see.
 *
 * The codebase had no answer to this. `users.branch_id` and the `branch_user`
 * pivot both existed, nothing reconciled them, and every branch filter in the
 * app was an optional query parameter the caller supplied — so any holder of
 * `inventory.viewAny` could read, and edit, every branch's stock.
 *
 * The rules, in one place:
 *
 *  - `branch_user` is authoritative. It is the many-to-many set of branches a
 *    user actually works at.
 *  - `users.branch_id` is only a primary/default branch. It is the fallback for
 *    a user who has no pivot rows yet, and what a form pre-selects.
 *  - super-admin sees everything. Gate::before already grants them every
 *    ability, but abilities are not query scope, so it has to be stated here too.
 *  - A user with neither gets an empty result set, never an error. Seeing no
 *    stock is a correct answer for someone assigned to no branch.
 */
class BranchScope
{
    /** @var array<int, list<int>|null> Per-request memo, keyed by user id. */
    private array $memo = [];

    /**
     * Branch ids this user may read, or null meaning "no restriction".
     *
     * Null and [] are deliberately different: null is super-admin (everything),
     * [] is a user attached to nothing (nothing).
     *
     * @return list<int>|null
     */
    public function readableBranchIds(User $user): ?array
    {
        $key = (int) $user->getKey();

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        if ($user->hasAnyRole(['super-admin', 'superadmin'])) {
            return $this->memo[$key] = null;
        }

        $ids = $user->branches()->pluck('branches.id')->all();

        if ($ids === [] && $user->branch_id !== null) {
            $ids = [$user->branch_id];
        }

        return $this->memo[$key] = array_values(array_unique(array_map('intval', $ids)));
    }

    public function canAccess(User $user, ?int $branchId): bool
    {
        if ($branchId === null) {
            return false;
        }

        $ids = $this->readableBranchIds($user);

        return $ids === null || in_array($branchId, $ids, true);
    }

    /**
     * Restrict a query to the user's branches.
     *
     * An empty allowlist becomes `whereIn(column, [])`, which Laravel compiles
     * to a false predicate — the user correctly sees nothing rather than
     * everything, which is the failure mode that matters here.
     */
    public function apply(Builder $query, User $user, string $column = 'branch_id'): Builder
    {
        $ids = $this->readableBranchIds($user);

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($column, $ids);
    }

    /** Forget the memo — only needed when memberships change mid-request. */
    public function forget(?User $user = null): void
    {
        if ($user === null) {
            $this->memo = [];

            return;
        }

        unset($this->memo[(int) $user->getKey()]);
    }
}
