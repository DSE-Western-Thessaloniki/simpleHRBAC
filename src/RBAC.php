<?php

namespace Dsewth\SimpleHRBAC;

use Dsewth\SimpleHRBAC\Exceptions\RBACException;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Dsewth\SimpleHRBAC\Models\Subject;
use Illuminate\Support\Collection;

class RBAC
{
    protected array $roles;

    public function __construct(protected ?array $config)
    {
    }

    public function loadJsonFile(string $filename)
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

        return $this->loadData($data);
    }

    public function loadData(array $data)
    {
        if (isset($data['Permissions'])) {
            if (! is_array($data['Permissions'])) {
                throw new RBACException("Array key 'Permissions' should be an array");
            }

            foreach ($data['Permissions'] as $row) {
                /** @var Permission $permission */
                $permission = Permission::create($row);
            }
        }

        if (isset($data['Roles'])) {
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

        if (isset($data['Subjects'])) {
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
