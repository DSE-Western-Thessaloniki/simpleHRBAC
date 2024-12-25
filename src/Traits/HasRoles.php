<?php

namespace Dsewth\SimpleHRBAC\Traits;

use Dsewth\SimpleHRBAC\Facades\RBAC;
use Dsewth\SimpleHRBAC\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function canUsingRBAC(string $permission): bool
    {
        // Κάνε χρήση του memoization του RBAC για να αποφύγουμε
        // την επανάληψη εκτέλεσης ερωτημάτων στη βάση
        return RBAC::can($this->id, $permission);
    }
}
