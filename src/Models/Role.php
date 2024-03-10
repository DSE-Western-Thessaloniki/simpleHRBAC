<?php

namespace Dsewth\SimpleHRBAC\Models;

class Role
{
    private string|int $id = -1;

    private string $name = '';

    private string $description = '';

    private string|int $parent = -1;

    private array $children = [];

    public static function withID(string|int $id): Role
    {
        $instance = new self();

        return $instance;
    }

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

    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function setDescription(string $description)
    {
        $this->description = $description;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function setParent(string|int $id): void
    {
        $this->parent = $id;
    }

    public function parent(): string|int
    {
        return $this->parent;
    }

    public function setChildren(array $children): void
    {
        $this->children = $children;
    }

    public function children(): array
    {
        return $this->children;
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
}
