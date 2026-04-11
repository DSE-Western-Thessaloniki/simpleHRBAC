<?php

namespace Dsewth\SimpleHRBAC\Helpers;

use Dsewth\SimpleHRBAC\Models\Role;
use Illuminate\Database\Query\Builder;
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
        DB::table($this->table)->insert([
            'parent' => $this->referenceRole->id,
            'child' => $this->referenceRole->id,
            'depth' => 0,
        ]);

        // Αν έχει γονικό κόμβο
        if ($this->referenceRole->parent_id) {
            DB::table($this->table)->insertUsing(
                ['parent', 'child', 'depth'],
                function (Builder $query) {
                    $query->select('p.parent', 'c.child', DB::raw('p.depth + c.depth + 1'))
                        ->from("{$this->table} as p")
                        ->crossJoin("{$this->table} as c")
                        ->where('p.child', $this->referenceRole->parent_id)
                        ->where('c.parent', $this->referenceRole->id);
                }
            );
        }
    }

    /**
     * Αφαίρεση κόμβου από το δέντρο
     */
    public function removeFromTree(): void
    {
        // Βρες όλους τους απογόνους του κόμβου
        $descendants = DB::table($this->table)
            ->select('child')
            ->where('parent', $this->referenceRole->id)
            ->get();
        $descendantIds = $descendants->pluck('child')->toArray();
        $allIds = array_unique(array_merge([$this->referenceRole->id], $descendantIds));

        // Αφαίρεση όλων των γραμμών που αναφέρονται σε αυτούς τους κόμβους
        DB::table($this->table)
            ->whereIn('child', $allIds)
            ->delete();
        DB::table($this->table)
            ->whereIn('parent', $allIds)
            ->delete();
    }

    /**
     * Επιστρέφει λίστα των παιδιών του κόμβου
     *
     * @return Collection<Role>
     */
    public function children($with = []): Collection
    {
        $roleTable = $this->referenceRole->getTable();

        $ids = DB::table($this->table)
            ->join($roleTable, "{$roleTable}.id", '=', "{$this->table}.child")
            ->where("{$this->table}.parent", $this->referenceRole->id)
            ->where("{$this->table}.child", '!=', $this->referenceRole->id)
            ->pluck("{$roleTable}.id");

        $result = Role::query()
            ->with($with)
            ->find($ids);

        return $result instanceof Collection ? $result : new Collection($result);
    }

    /**
     * Επιστρέφει λίστα των παιδιών του κόμβου
     *
     * @return Collection<Role>
     */
    public function immediateChildren($with = []): Collection
    {
        $roleTable = $this->referenceRole->getTable();

        $ids = DB::table($this->table)
            ->join($roleTable, "{$roleTable}.id", '=', "{$this->table}.child")
            ->where("{$this->table}.parent", $this->referenceRole->id)
            ->where("{$this->table}.child", '!=', $this->referenceRole->id)
            ->where("{$this->table}.depth", 1)
            ->pluck("{$roleTable}.id");

        $result = Role::query()
            ->with($with)
            ->find($ids);

        return $result instanceof Collection ? $result : new Collection($result);
    }

    /**
     * Επιστρέφει τους γονικούς κόμβους του κόμβου
     *
     * @return Collection<Role>
     */
    public function parents($with = []): Collection
    {
        $roleTable = $this->referenceRole->getTable();

        $ids = DB::table($this->table)
            ->join($roleTable, "{$roleTable}.id", '=', "{$this->table}.parent")
            ->where("{$this->table}.child", $this->referenceRole->id)
            ->where("{$roleTable}.id", '!=', $this->referenceRole->id)
            ->pluck("{$roleTable}.id");

        $result = Role::query()
            ->with($with)
            ->find($ids);

        return $result instanceof Collection ? $result : new Collection($result);
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
                    WHERE parent = ?
                ) AND
                parent IN
                (
                    SELECT parent
                    FROM {$this->table}
                    WHERE child = ? AND
                        parent != child
                )",
            [$this->referenceRole->id, $this->referenceRole->id]
        );

        // και έπειτα χτίσε από την αρχή τις σχέσεις
        DB::table($this->table)->insertUsing(
            ['parent', 'child', 'depth'],
            function ($query) {
                $query->select('supertree.parent', 'subtree.child', DB::raw('supertree.depth + subtree.depth + 1'))
                    ->from("{$this->table} as supertree")
                    ->crossJoin("{$this->table} as subtree")
                    ->where('supertree.child', $this->referenceRole->parent_id)
                    ->where('subtree.parent', $this->referenceRole->id);
            }
        );
    }
}
