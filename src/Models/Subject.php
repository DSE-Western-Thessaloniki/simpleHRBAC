<?php

namespace Dsewth\SimpleHRBAC\Models;

use Dsewth\SimpleHRBAC\RBAC;

class Subject
{
    private string|int $id = -1;

    private string $name = '';

    /** @var array<string|int> */
    private array $roles = [];

    public function id(): string|int
    {
        return $this->id;
    }

    public function setId(string|int $id): void
    {
        $this->id = $id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Return the roles of the subject
     *
     * @return array<string|int>
     */
    public function roles(): array
    {
        return $this->roles;
    }

    /**
     * Set the roles of the subject
     *
     * @param  array<string|int>  $role
     */
    public function setRoles(array $role): void
    {
        $this->roles = [...$role];
    }

    public static function fromRow(array $row)
    {
        $instance = new self();

        $instance->setId($row['id']);
        $instance->setName($row['name']);
        $instance->setRoles($row['roles']);

        return $instance;
    }

    public static function find(string|int $id)
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
