<?php

namespace Dsewth\SimpleHRBAC\Models;

use Dsewth\SimpleHRBAC\RBAC;

class Subject extends BaseModel
{
    private $id = 0;

    private string $name = '';

    private array $roles = [];

    protected static string $table = 'subjects';

    protected array $columns = ['id', 'name'];

    protected string $rolesTable = 'subjects_roles';

    protected array $rolesTableColumns = ['subject_id', 'role_id'];

    public function id()
    {
        return $this->id;
    }

    public function setId($id): Subject
    {
        $this->id = $id;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function setName(string $name): Subject
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Return the roles of the subject
     */
    public function roles(): array
    {
        return $this->roles;
    }

    /**
     * Set the roles of the subject
     */
    public function setRoles(array $roles): Subject
    {
        $this->roles = [...$roles];

        return $this;
    }

    public static function fromRow(array $row): Subject
    {
        $instance = new self();

        $instance->setId($row['id']);
        $instance->setName($row['name']);
        $instance->setRoles($row['roles'] ?? []);

        return $instance;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'roles' => $this->roles,
        ];
    }

    public function save()
    {
        [$id] = RBAC::getInstance()->database()->saveRows(static::$table, $this->columns, $this->toArray());

        $this->id = $id;
    }
}
