<?php

namespace Dsewth\SimpleHRBAC\Traits;

use Dsewth\SimpleHRBAC\Facades\RBAC;
use Dsewth\SimpleHRBAC\Models\Role;
use Dsewth\SimpleHRBAC\Models\RoleUser;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->using(RoleUser::class);
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
        return RBAC::can($this->id, $permission);
    }

    public function invalidateRBACCache(): void
    {
        if (app()->bound('rbac.service')) {
            app('rbac.service')->invalidateUserCache($this->id);
        }
    }
}
