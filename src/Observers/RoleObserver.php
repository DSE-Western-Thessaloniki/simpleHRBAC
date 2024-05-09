<?php

namespace Dsewth\SimpleHRBAC\Observers;

use Dsewth\SimpleHRBAC\Exceptions\RBACException;
use Dsewth\SimpleHRBAC\Models\Role;

class RoleObserver
{
    public function creating(Role $role): void
    {
        // Έλεγξε αν υπάρχει άλλος κόμβος χωρίς γονέα
        if ($role->parent_id === null && Role::where('parent_id', null)->count() === 1) {
            throw new RBACException('Only the root node can have null parent_id');
        }
    }

    /**
     * Handle the Role "created" event.
     */
    public function created(Role $role): void
    {
        if ($role->parent_id !== null && $role->parent_id === $role->id) {
            $role->delete();
            throw new RBACException('A node cannot be its own parent');
        }

        $role->tree()->addToTree();
    }

    public function updating(Role $role): void
    {
        if (! $role->isDirty('parent_id')) {
            return;
        }

        if ($role->parent_id === null) {
            throw new RBACException('Only the root node can have null parent_id');
        }

        if ($role->parent_id === $role->id) {
            throw new RBACException('A node cannot be its own parent');
        }

        if (Role::where('parent_id', null)->first()->id === $role->id) {
            throw new RBACException('You cannot move root node');
        }
    }

    /**
     * Handle the Role "updated" event.
     */
    public function updated(Role $role): void
    {
        if (! $role->wasChanged('parent_id')) {
            return;
        }

        $role->tree()->moveNode();
    }

    public function deleting(Role $role): void
    {
        if ($role->parent_id === null) {
            throw new RBACException('The root node cannot be removed from the tree');
        }

        // Όταν διαγράφουμε έναν ρόλο, μεταφέρουμε τα παιδιά του στον γονέα
        $role->immediateChildren()->each(function ($child) use ($role) {
            $child->parent_id = $role->parent_id;
            $child->save();
            $child->tree()->moveNode();
        });

        $role->tree()->removeFromTree();

        $role->subjects()->detach();
        $role->permissions()->detach();

    }

    /**
     * Handle the Role "deleted" event.
     */
    public function deleted(Role $role): void
    {
    }

    /**
     * Handle the Role "restored" event.
     */
    public function restored(Role $role): void
    {
        $this->created($role);
    }

    /**
     * Handle the Role "forceDeleted" event.
     */
    public function forceDeleted(Role $role): void
    {
        $this->deleted($role);
    }
}
