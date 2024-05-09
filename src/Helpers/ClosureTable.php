<?php

namespace Dsewth\SimpleHRBAC\Helpers;

use Dsewth\SimpleHRBAC\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Κλάση υλοποίησης closure table για αναπαράσταση
 * δενδρικής δομής ρόλων. Κατά τη δημιουργία της κλάσης
 * δίνουμε τον κόμβο βάση του οποίου θα πραγματοποιήσουμε
 * τις διάφορες ενέργειες (όχι απαραίτητα τον ριζικό κόμβο).
 */
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

    /**
     * Προσθήκη νέου κόμβου μετά τη δημιουργία νέου ρόλου
     *
     * @return void
     */
    public function addToTree()
    {
        DB::insert(
            "INSERT INTO {$this->table} (parent, child, depth) 
			VALUES ({$this->referenceRole->id}, {$this->referenceRole->id}, 0)"
        );

        // Αν έχει γονικό κόμβο
        if ($this->referenceRole->parent_id) {
            DB::insert(
                "INSERT INTO {$this->table} (parent, child, depth)
				SELECT p.parent, c.child, p.depth + c.depth + 1
				FROM {$this->table} p, {$this->table} c
				WHERE p.child = \"{$this->referenceRole->parent_id}\" AND 
                    c.parent = \"{$this->referenceRole->id}\""
            );
        }
    }

    /**
     * Αφαίρεση κόμβου από το δέντρο
     */
    public function removeFromTree(): void
    {
        DB::delete(
            "DELETE FROM {$this->table} 
			WHERE child = \"{$this->referenceRole->id}\""
        );

        DB::delete(
            "DELETE FROM {$this->table} 
			WHERE child IN (
				SELECT child
				FROM {$this->table}
				WHERE parent = \"{$this->referenceRole->id}\"
            )"
        );
    }

    /**
     * Επιστρέφει λίστα των παιδιών του κόμβου
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
                t.child != \"{$this->referenceRole->id}\""
        );

        $result = array_map(function ($item) {
            return Role::find($item->id);
        }, $result);

        return new Collection($result);
    }

    /**
     * Επιστρέφει λίστα των παιδιών του κόμβου
     *
     * @return Collection<Role>
     */
    public function immediateChildren(): Collection
    {
        $result = DB::select(
            "SELECT {$this->referenceRole->getTable()}.id
			FROM {$this->referenceRole->getTable()}
			JOIN {$this->table} as t
			ON {$this->referenceRole->getTable()}.id = t.child
			WHERE t.parent = \"{$this->referenceRole->id}\" AND
                t.child != \"{$this->referenceRole->id}\" AND t.depth = 1"
        );

        $result = array_map(function ($item) {
            return Role::find($item->id);
        }, $result);

        return new Collection($result);
    }

    /**
     * Επιστρέφει τους γονικούς κόμβους του κόμβου
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

    /**
     * Μετακίνηση του κόμβου μετά την αλλαγή του πατέρα
     */
    public function moveNode(): void
    {
        // Αφαίρεσε αρχικά τους κόμβους που σχετίζονται
        // με τον συγκεκριμένο
        DB::delete("DELETE FROM {$this->table}
                WHERE child IN
                (
                    SELECT child
                    FROM {$this->table}
                    WHERE parent = \"{$this->referenceRole->id}\"
                ) AND 
                parent IN
                (
                    SELECT parent
                    FROM {$this->table}
                    WHERE child = \"{$this->referenceRole->id}\" AND
                        parent != child
                )"
        );

        // και έπειτα χτίσε από την αρχή τις σχέσεις
        DB::insert(
            "INSERT INTO {$this->table} (parent, child, depth)
                SELECT supertree.parent, subtree.child, 
                    supertree.depth + subtree.depth + 1
                FROM {$this->table} AS supertree
                JOIN {$this->table} AS subtree
                WHERE supertree.child = \"{$this->referenceRole->parent_id}\" AND
                    subtree.parent = \"{$this->referenceRole->id}\""
        );
    }
}
