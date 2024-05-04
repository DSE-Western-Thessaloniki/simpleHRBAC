<?php

namespace Dsewth\SimpleHRBAC\Helpers;

use Dsewth\SimpleHRBAC\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClosureTable
{
    protected ?Role $referenceRole = null;

    protected string $table = 'role_tree';

    public function __construct(?Role $referenceRole = null)
    {
        if ($referenceRole) {
            $this->referenceRole = $referenceRole;
        }
    }

    public function addToTree()
    {
        DB::insert("INSERT INTO {$this->table} (parent, child, depth) 
			VALUES (?, ?, 0)", [
            $this->referenceRole->id,
            $this->referenceRole->id,
        ]);

        // Αν έχει γονικό κόμβο
        if ($this->referenceRole->parent_id) {
            DB::insert("INSERT INTO {$this->table} (parent, child, depth)
				SELECT p.parent, c.child, p.depth + c.depth + 1
				FROM {$this->table} p, {$this->table} c
				WHERE p.child =? AND c.parent =?", [
                $this->referenceRole->parent_id,
                $this->referenceRole->id,
            ]);
        }
    }

    public function removeFromTree(): void
    {
        DB::delete(
            "DELETE FROM {$this->table} 
			WHERE child = ?", [
                $this->referenceRole->id,
            ]
        );

        DB::delete(
            "DELETE FROM {$this->table} 
			WHERE child IN (
				SELECT child
				FROM {$this->table}
				WHERE parent = ?
			)", [
                $this->referenceRole->id,
            ]
        );
    }

    /**
     * Return the children of the referenceRole
     *
     * @return Collection<Role>
     */
    public function children(): Collection
    {
        $result = DB::select(
            "SELECT {$this->referenceRole->getTable()}.id
			FROM {$this->referenceRole->getTable()}
			JOIN {$this->table} as t
			ON {$this->referenceRole->getTable()}.id = t.child
			WHERE t.parent = \"{$this->referenceRole->id}\" AND
                {$this->referenceRole->getTable()}.id != \"{$this->referenceRole->id}\""
        );

        $result = array_map(function ($item) {
            return Role::find($item->id);
        }, $result);

        return new Collection($result);
    }

    /**
     * Return the parents of the referenceRole
     *
     * @return Collection<Role>
     */
    public function parents(): Collection
    {
        $result = DB::select(
            "SELECT {$this->referenceRole->getTable()}.id
			FROM {$this->referenceRole->getTable()}
			JOIN {$this->table} as t
			ON {$this->referenceRole->getTable()}.id = t.parent
			WHERE t.child = \"{$this->referenceRole->id}\" AND
                {$this->referenceRole->getTable()}.id != \"{$this->referenceRole->id}\""
        );

        $result = array_map(function ($item) {
            return Role::find($item->id);
        }, $result);

        return new Collection($result);
    }
}
