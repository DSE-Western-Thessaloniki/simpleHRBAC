<?php

namespace Dsewth\SimpleHRBAC\Traits;

use Dsewth\SimpleHRBAC\Facades\RBAC;
use Dsewth\SimpleHRBAC\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Επιστρέφει μια συλλογή με τα δικαιώματα του χρήστη από όλους
     * τους ρόλους του.
     *
     * @return Collection<Dsewth\SimpleHRBAC\Models\Permission>
     */
    public function permissions(): Collection
    {
        return RBAC::getPermissionsOf($this);
    }

    public function canUsingRBAC(string $permission): bool
    {
        // Κάνε χρήση του memoization του RBAC για να αποφύγουμε
        // την επανάληψη εκτέλεσης ερωτημάτων στη βάση
        return RBAC::can($this->id, $permission);
    }
}
