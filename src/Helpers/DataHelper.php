<?php

namespace Dsewth\SimpleHRBAC\Helpers;

use Dsewth\SimpleHRBAC\Exceptions\RBACException;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Illuminate\Database\Eloquent\Model;

class DataHelper
{
    protected static $userModel;

    /**
     * Set the user model class to be used by the helper
     */
    public static function useUserModel(string $modelClass): void
    {
        if (! is_subclass_of($modelClass, Model::class)) {
            throw new \InvalidArgumentException("The provided class '$modelClass' must be a valid Eloquent model.");
        }

        static::$userModel = $modelClass;
    }

    /**
     * Get the user model class being used
     */
    public static function getUserModelClass(): string
    {
        return static::$userModel ?? config('package-name.user_model', \App\Models\User::class);
    }

    public static function importJsonFile(string $filename)
    {
        if (! file_exists($filename) || ! is_readable($filename)) {
            return false;
        }

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

    private static function importUsers(array $data): void
    {
        if (! isset($data['Users'])) {
            return;
        }

        if (! is_array($data['Users'])) {
            throw new RBACException("Array key 'Users' should be an array");
        }

        $userModelClass = static::getUserModelClass();
        foreach ($data['Users'] as $row) {
            $user = $userModelClass::create($row);

            if (isset($row['roles'])) {
                foreach ($row['roles'] as $role) {
                    $user->roles()->save(Role::find($role));
                }
            }
        }
    }

    public static function importData(array $data)
    {
        self::importPermissions($data);
        self::importRoles($data);
        self::importUsers($data);

        return true;
    }
}
