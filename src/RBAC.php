<?php

namespace Dsewth\SimpleHRBAC;

use Dsewth\SimpleHRBAC\Database\Database;
use Dsewth\SimpleHRBAC\Database\DriverInterface;
use Dsewth\SimpleHRBAC\Exceptions\RBACException;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Dsewth\SimpleHRBAC\Models\Subject;

class RBAC
{
    private static $instance = null;

    protected Database $db;

    protected array $roles;

    protected function __construct(DriverInterface $databaseDriver)
    {
        $this->db = new Database($databaseDriver);
    }

    public static function destroy()
    {
        static::$instance = null;
    }

    public static function initialize(DriverInterface $driver): RBAC
    {
        if (! isset(self::$instance)) {
            self::$instance = new self($driver);

            return self::$instance;
        } else {
            self::$instance->setDatabaseDriver($driver);

            return self::$instance;
        }
    }

    private function setDatabaseDriver($driver)
    {
        $this->db = new Database($driver);
    }

    public static function getInstance(): RBAC
    {
        if (! isset(self::$instance)) {
            throw new RBACException('HRBAC not initialized! Please use initialize() instead.');
        }

        return self::$instance;
    }

    public function database(): Database
    {
        return $this->db;
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
                // $id = $permission['id'];
                // if (array_key_exists($id, $this->data['Permissions'])) {
                //     throw new RBACException("Duplicate id $id for permissions {$permission['name']} and {$this->data['Permissions'][$id]->name()}");
                // }

                // $this->data['Permissions'][$id] = $permission;

                /** @var Permission $permission */
                $permission = Permission::fromData($row);
                $permission->save();
            }
        }

        if (isset($data['Roles'])) {
            if (! is_array($data['Roles'])) {
                throw new RBACException("Array key 'Roles' should be an array");
            }

            foreach ($data['Roles'] as $row) {
                // $id = $role['id'];
                // if (array_key_exists($id, $this->data['Roles'])) {
                //     throw new RBACException("Duplicate id $id for permissions {$role['name']} and {$this->data['Roles'][$id]->name()}");
                // }

                // $this->data['Roles'][$id] = $role;

                /** @var Role $role */
                $role = Role::fromData($row);
                $role->save();
            }
        }

        if (isset($data['Subjects'])) {
            if (! is_array($data['Subjects'])) {
                throw new RBACException("Array key 'Subjects' should be an array");
            }

            foreach ($data['Subjects'] as $row) {
                // $id = $subject['id'];
                // if (array_key_exists($id, $this->data['Subjects'])) {
                //     throw new RBACException("Duplicate id $id for permissions {$subject['name']} and {$this->data['Subjects'][$id]->name()}");
                // }

                // $this->data['Subjects'][$id] = $subject;

                /** @var Subject $subject */
                $subject = Subject::fromData($row);
                $subject->save();
            }
        }

        return true;
    }

    public function addRole($role)
    {

    }

    public function removeRole($role)
    {

    }

    public function addPermission($permission)
    {

    }

    public function removePermission($permission)
    {

    }

    public function hasPermission($role, $permission)
    {

    }
}
