<?php

namespace Dsewth\SimpleHRBAC\Models;

use Dsewth\SimpleHRBAC\RBAC;

class Permission
{
    private $id = 0;

    private string $name = '';

    public static function fromRow(array $row)
    {
        $instance = new self();

        $instance->setId($row['id']);
        $instance->setName($row['name']);

        return $instance;
    }

    public function id()
    {
        return $this->id;
    }

    public function setId($id): Permission
    {
        $this->id = $id;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function setName(string $name): Permission
    {
        $this->name = $name;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }

    public function save(): void
    {
        [$id] = RBAC::getInstance()->database()->savePermissions($this->toArray());

        $this->id = $id;
    }
}
