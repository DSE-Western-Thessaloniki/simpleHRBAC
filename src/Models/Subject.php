<?php

namespace Dsewth\SimpleHRBAC\Models;

use Dsewth\SimpleHRBAC\RBAC;

class Subject
{
    private $id = 0;

    private string $name = '';

    private array $roles = [];

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
    public function setRoles(array $role): Subject
    {
        $this->roles = [...$role];

        return $this;
    }

    public static function fromRow(array $row)
    {
        $instance = new self();

        $instance->setId($row['id']);
        $instance->setName($row['name']);
        $instance->setRoles($row['roles']);

        return $instance;
    }

    public static function find($id)
    {
        return RBAC::getInstance()->getSubjectById($id);
    }

    public static function findName(string $name)
    {
        return RBAC::getInstance()->getSubjectsByName($name);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'roles' => $this->roles,
        ];
    }
}
