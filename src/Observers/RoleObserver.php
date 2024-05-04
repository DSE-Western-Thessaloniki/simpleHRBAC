<?php

namespace Dsewth\SimpleHRBAC\Observers;

use Dsewth\SimpleHRBAC\Models\Role;

class RoleObserver
{
    /**
     * Handle the Role "created" event.
     */
    public function created(Role $role): void
    {
        $role->tree()->addToTree();
    }

    /**
     * Handle the Role "updated" event.
     */
    public function updated(Role $role): void
    {
        if (! $role->wasChanged('parent_id')) {
            return;
        }
    }

    /**
     * Handle the Role "deleted" event.
     */
    public function deleted(Role $role): void
    {
        // Όταν διαγράφουμε έναν ρόλο, διαγράφουμε και όλα τα παιδιά του
        $role->children()->each(fn ($child) => $child->delete());
        $role->tree()->removeFromTree();
    }

    /**
     * Handle the Role "restored" event.
     */
    public function restored(Role $role): void
    {
        // ...
    }

    /**
     * Handle the Role "forceDeleted" event.
     */
    public function forceDeleted(Role $role): void
    {
        // ...
    }
}
