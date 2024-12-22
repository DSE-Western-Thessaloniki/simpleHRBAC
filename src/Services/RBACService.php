<?php

namespace Dsewth\SimpleHRBAC\Services;

use Dsewth\SimpleHRBAC\Exceptions\RBACException;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Dsewth\SimpleHRBAC\Models\Subject;
use Illuminate\Support\Collection;

class RBACService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge(config('simple-hrbac', []), $config);
    }

    public function importJsonFile(string $filename)
    {
        $data = file_get_contents($filename);
        if (! $data) {
            // File read failed
            return false;
        }

        $data = json_decode($data, true);
        if (is_null($data)) {
            // Json decode failed
            return false;
        }

        return $this->importData($data);
    }

    private function importPermissions(array $data): void
    {
        if (! isset($data['Permissions'])) {
            return;
        }

        if (! is_array($data['Permissions'])) {
            throw new RBACException("Array key 'Permissions' should be an array");
        }

        foreach ($data['Permissions'] as $row) {
            Permission::create($row);
        }
    }

    private function importRoles(array $data): void
    {
        if (! isset($data['Roles'])) {
            return;
        }

        if (! is_array($data['Roles'])) {
            throw new RBACException("Array key 'Roles' should be an array");
        }

        foreach ($data['Roles'] as $row) {
            /** @var Role $role */
            $role = Role::create($row);

            if (isset($row['permissions'])) {
                foreach ($row['permissions'] as $permissionId) {
                    $permission = Permission::find($permissionId);
                    $role->permissions()->save($permission);
                }
            }
        }
    }

    private function importSubjects(array $data): void
    {
        if (! isset($data['Subjects'])) {
            return;
        }

        if (! is_array($data['Subjects'])) {
            throw new RBACException("Array key 'Subjects' should be an array");
        }

        foreach ($data['Subjects'] as $row) {
            /** @var Subject $subject */
            $subject = Subject::create($row);

            if (isset($row['roles'])) {
                foreach ($row['roles'] as $role) {
                    $subject->roles()->save(Role::find($role));
                }
            }
        }
    }

    public function importData(array $data)
    {
        $this->importPermissions($data);
        $this->importRoles($data);
        $this->importSubjects($data);

        return true;
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

    public function can(Subject $subject, Permission $permission): bool
    {
        $subjectPermissions = $this->getPermissionsOf($subject);

        return $subjectPermissions->contains('id', $permission->id);
    }
}
