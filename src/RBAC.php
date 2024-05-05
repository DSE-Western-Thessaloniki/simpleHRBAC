<?php

namespace Dsewth\SimpleHRBAC;

use Dsewth\SimpleHRBAC\Exceptions\RBACException;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Dsewth\SimpleHRBAC\Models\Subject;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Events\Dispatcher;

class RBAC
{
    protected ?Manager $db;

    protected array $roles;

    public function __construct(?array $config)
    {
        if (! array_key_exists('database', $config)) {
            throw new RBACException('No database configuration specified!');
        }

        if ($config && isset($config['database']) && ! app()->isBooted()) {
            $this->db = new Manager();
            $default = $config['database']['default'];
            $this->db->addConnection($config['database']['connections'][$default]);
            $this->db->setEventDispatcher(new Dispatcher(new Container));
            $this->db->bootEloquent();
        }
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
                $permission->save();
            }
        }

        if (isset($data['Roles'])) {
            if (! is_array($data['Roles'])) {
                throw new RBACException("Array key 'Roles' should be an array");
            }

            foreach ($data['Roles'] as $row) {
                /** @var Role $role */
                $role = Role::create($row);
                $role->save();

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
                $subject->save();

                if (isset($row['roles'])) {
                    foreach ($row['roles'] as $role) {
                        $subject->roles()->save(Role::find($role));
                    }
                }
            }
        }

        return true;
    }
}
