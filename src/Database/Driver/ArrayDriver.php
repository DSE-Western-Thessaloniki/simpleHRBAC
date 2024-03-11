<?php

namespace Dsewth\SimpleHRBAC\Database\Driver;

use Dsewth\SimpleHRBAC\Database\DriverInterface;
use Dsewth\SimpleHRBAC\Exceptions\RBACException;

class ArrayDriver implements DriverInterface
{
    private array $data;

    public function __construct()
    {
        $this->data = [
            'Permissions' => [],
            'Roles' => [],
            'Subjects' => [],
        ];
    }

    public function driverName(): string
    {
        return 'ArrayDriver';
    }

    public static function withData(array $data): ArrayDriver
    {
        $instance = new self();
        $instance->fill($data);

        return $instance;
    }

    protected function fill(array $data)
    {
        if (isset($data['Permissions'])) {
            if (! is_array($data['Permissions'])) {
                throw new RBACException("Array key 'Permissions' should be an array");
            }

            foreach ($data['Permissions'] as $permission) {
                $id = $permission['id'];
                if (array_key_exists($id, $this->data['Permissions'])) {
                    throw new RBACException("Duplicate id $id for permissions {$permission['name']} and {$this->data['Permissions'][$id]->name()}");
                }

                $this->data['Permissions'][$id] = $permission;
            }
        }

        if (isset($data['Roles'])) {
            if (! is_array($data['Roles'])) {
                throw new RBACException("Array key 'Roles' should be an array");
            }

            foreach ($data['Roles'] as $role) {
                $id = $role['id'];
                if (array_key_exists($id, $this->data['Roles'])) {
                    throw new RBACException("Duplicate id $id for permissions {$role['name']} and {$this->data['Roles'][$id]->name()}");
                }

                $this->data['Roles'][$id] = $role;
            }
        }

        if (isset($data['Subjects'])) {
            if (! is_array($data['Subjects'])) {
                throw new RBACException("Array key 'Subjects' should be an array");
            }

            foreach ($data['Subjects'] as $subject) {
                $id = $subject['id'];
                if (array_key_exists($id, $this->data['Subjects'])) {
                    throw new RBACException("Duplicate id $id for permissions {$subject['name']} and {$this->data['Subjects'][$id]->name()}");
                }

                $this->data['Subjects'][$id] = $subject;
            }
        }
    }

    private function satisfies($item, $filter): bool
    {
        if (count($filter) === 1 && array_key_exists(0, $filter) && $filter[0] === '*') {
            return true;
        }

        $result = true;
        foreach (array_keys($filter) as $key) {
            if (is_int($key)) {
                throw new RBACException('Invalid filter: '.$filter[$key]);
            }

            if (! is_array($filter[$key]) || count($filter[$key]) === 1) {
                $result &= $item[$key] === $filter[$key];

                continue;
            }

            if (count($filter[$key]) === 2) {
                if ($filter[$key][0] === '>') {
                    $result &= $item[$key] > $filter[$key][1];
                } elseif ($filter[$key][0] === '>=') {
                    $result &= $item[$key] >= $filter[$key][1];
                } elseif ($filter[$key][0] === '<') {
                    $result &= $item[$key] < $filter[$key][1];
                } elseif ($filter[$key][0] === '<=') {
                    $result &= $item[$key] <= $filter[$key][1];
                } elseif ($filter[$key][0] === '=') {
                    $result &= $item[$key] === $filter[$key][1];
                } elseif ($filter[$key][0] === '!=') {
                    $result &= $item[$key] !== $filter[$key][1];
                } elseif ($filter[$key][0] === 'like') {
                    if ($key === 'id') {
                        throw new RBACException('Invalid filter: '.$filter[$key]);
                    }
                    $result &= str_contains($item[$key], $filter[$key][1]);
                } else {
                    throw new RBACException('Invalid filter: '.$filter[$key]);
                }

                continue;
            }

            throw new RBACException('Invalid filter: '.$filter[$key]);
        }

        return $result;
    }

    public function permissions(array $filter = ['*']): array
    {
        $result = [];

        foreach ($this->data['Permissions'] as $permission) {
            if ($this->satisfies($permission, $filter)) {
                $result[] = [...$permission];
            }
        }

        return $result;
    }

    public function roles(array $filter = ['*']): array
    {
        $result = [];

        foreach ($this->data['Roles'] as $role) {
            if ($this->satisfies($role, $filter)) {
                $result[] = [...$role];
            }
        }

        return $result;
    }

    public function subjects(array $filter = ['*']): array
    {
        $result = [];

        foreach ($this->data['Subjects'] as $subject) {
            if ($this->satisfies($subject, $filter)) {
                $result[] = [...$subject];
            }
        }

        return $result;
    }

    private function savePermission(array $fields)
    {
        $filtered = array_filter($fields, function ($field) {
            return in_array($field, ['id', 'name']);
        }, ARRAY_FILTER_USE_KEY);

        $id = $filtered['id'];
        if ($id === 0) {
            // Find the first available id
            $keys = array_keys($this->data['Permissions']);
            if (count($keys) === 0) {
                $id = 1;
            } else {
                $id = max($keys) + 1;
            }
        }

        $filtered['id'] = $id;
        $this->data['Permissions'][$id] = $filtered;

        return $id;
    }

    public function savePermissions(array $permissions): array
    {
        if (! array_key_exists(0, $permissions)) {
            return [$this->savePermission($permissions)];
        }

        $ids = [];
        foreach ($permissions as $permission) {
            $ids[] = $this->savePermission($permission);
        }

        return $ids;
    }

    private function saveRole(array $fields)
    {
        $filtered = array_filter($fields, function ($field) {
            return in_array($field, ['id', 'name', 'description', 'parent', 'children']);
        }, ARRAY_FILTER_USE_KEY);

        $id = $filtered['id'];
        if ($id === 0) {
            // Find the first available id
            $keys = array_keys($this->data['Roles']);
            if (count($keys) === 0) {
                $id = 1;
            } else {
                $id = max($keys) + 1;
            }
        }

        $filtered['id'] = $id;
        $this->data['Roles'][$id] = $filtered;

        return $id;
    }

    public function saveRoles(array $roles): array
    {
        if (! array_key_exists(0, $roles)) {
            return [$this->saveRole($roles)];
        }

        $ids = [];
        foreach ($roles as $role) {
            $ids[] = $this->saveRole($role);
        }

        return $ids;
    }
}
