<?php

namespace Dsewth\SimpleHRBAC\Models;

use Dsewth\SimpleHRBAC\RBAC;

class Subject
{
    private string|int $id = -1;

    private string $name = '';

    private string|int $role = -1;

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

    public function role(): string|int
    {
        return $this->role;
    }

    public function setRole(string|int $role): void
    {
        $this->role = $role;
    }

    public static function fromRow(array $row)
    {
        $instance = new self();

        $instance->setId($row['id']);
        $instance->setName($row['name']);
        $instance->setRole($row['role']);

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
            'role_id' => $this->role,
        ];
    }
}
