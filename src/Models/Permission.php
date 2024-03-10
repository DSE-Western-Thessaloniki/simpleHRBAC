<?php

namespace Dsewth\SimpleHRBAC\Models;

use Dsewth\SimpleHRBAC\RBAC;

class Permission
{
    private string|int $id = -1;

    private string $name = '';

    public static function fromRow(array $row)
    {
        $instance = new self();

        $instance->setId($row['id']);
        $instance->setName($row['name']);

        return $instance;
    }

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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }

    public function save(): void
    {
        RBAC::getInstance()->database()->savePermissions($this->toArray());
    }
}
