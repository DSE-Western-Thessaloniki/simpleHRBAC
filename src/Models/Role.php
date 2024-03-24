<?php

namespace Dsewth\SimpleHRBAC\Models;

use Dsewth\SimpleHRBAC\RBAC;

class Role extends BaseModel
{
    private $id = 0;

    private string $name = '';

    private string $description = '';

    private $parent = 0;

    private array $children = [];

    protected static string $table = 'roles';

    protected array $columns = ['id', 'name', 'description', 'parent', 'children'];

    protected string $closureTable = 'roles_closure';

    protected array $closureTableColumns = ['parent', 'child', 'depth'];

    protected string $permissionsTable = 'roles_permissions';

    protected array $permissionsTableColumns = ['role_id', 'permission_id'];

    public static function fromRow(array $row): Role
    {
        $instance = new self();

        $instance->setId($row['id']);
        $instance->setName($row['name']);
        $instance->setDescription($row['description'] ?? '');
        $instance->setParent($row['parent'] ?? -1);
        $instance->setChildren($row['children'] ?? []);

        return $instance;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function id()
    {
        return $this->id;
    }

    public function setName(string $name): Role
    {
        $this->name = $name;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function setDescription(string $description): Role
    {
        $this->description = $description;

        return $this;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function setParent($id): Role
    {
        $this->parent = $id;

        return $this;
    }

    public function parent()
    {
        return $this->parent;
    }

    public function setChildren(array $children): Role
    {
        $this->children = $children;

        return $this;
    }

    public function addPermission(Permission $permission)
    {
        RBAC::getInstance()
            ->database()
            ->saveRows($this->permissionsTable, $this->permissionsTableColumns, ['role_id' => $this->id, 'permission_id' => $permission->id()]);
    }

    public function children(): array
    {
        return $this->children;
    }

    public function permissions(): array
    {
        $permissions = RBAC::getInstance()
            ->database()
            ->select($this->permissionsTable, ['role_id' => $this->id()]);

        return array_map(function ($row) {
            return Permission::find($row);
        }, $permissions);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'parent' => $this->parent,
            'children' => $this->children,
        ];
    }

    public function save()
    {
        [$id] = RBAC::getInstance()->database()->saveRows(static::$table, $this->columns, $this->toArray());

        $this->id = $id;
    }
}
