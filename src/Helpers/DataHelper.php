<?php

namespace Dsewth\SimpleHRBAC\Helpers;

use Dsewth\SimpleHRBAC\Exceptions\RBACException;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Dsewth\SimpleHRBAC\Models\Subject;

class DataHelper
{
    public static function importJsonFile(string $filename)
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

        return self::importData($data);
    }

    private static function importPermissions(array $data): void
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

    private static function importRoles(array $data): void
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

    private static function importSubjects(array $data): void
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

    public static function importData(array $data)
    {
        self::importPermissions($data);
        self::importRoles($data);
        self::importSubjects($data);

        return true;
    }
}
