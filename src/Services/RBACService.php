<?php

namespace Dsewth\SimpleHRBAC\Services;

use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Subject;
use Illuminate\Support\Collection;

class RBACService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge(config('simple-hrbac', []), $config);
    }

    /**
     * Επέστρεψε μια συλλογή των δικαιωμάτων ενός υποκειμένου
     *
     * @return Dsewth\SimpleHRBAC\Collection<Permission>
     */
    public function getPermissionsOf(Subject $subject): Collection
    {
        $permissions = new Collection;
        foreach ($subject->roles as $role) {
            foreach ($role->permissions as $permission) {
                $permissions->push($permission);
            }

            // Πρέπει να ελέγξουμε και τα παιδιά του ρόλου
            foreach ($role->children() as $child) {
                foreach ($child->permissions as $permission) {
                    $permissions->push($permission);
                }
            }
        }

        return $permissions->unique('id');
    }

    /**
     * Επέστρεψε true αν ένα υποκείμενο έχει το δικαίωμα, αλλιώς false
     *
     * @param  int  $subjectId  Κωδικός Υποκειμένου
     * @param  string  $permission  Όνομα δικαιώματος
     */
    public function can(int $subjectId, string $permission): bool
    {
        return once(function () use ($subjectId, $permission) {
            if (Permission::where('name', $permission)->exists()) {
                $subjectPermissions = $this->getPermissionsOf(Subject::find($subjectId));

                return $subjectPermissions->contains('name', $permission);
            }

            return false;
        });
    }
}
