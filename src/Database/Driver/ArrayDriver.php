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

    public function select(string $table, array $filter = ['*']): array
    {
        $result = [];

        foreach ($this->data[$table] as $row) {
            if ($this->satisfies($row, $filter)) {
                $result[] = [...$row];
            }
        }

        return $result;
    }

    public function saveRow(string $table, array $columns, array $row)
    {
        $filtered = array_filter($row, function ($field) use ($columns) {
            return in_array($field, $columns);
        }, ARRAY_FILTER_USE_KEY);

        if (! isset($this->data[$table])) {
            $this->data[$table] = [];
        }

        $id = $filtered['id'];
        if ($id === 0) {
            // Find the first available id
            $keys = array_keys($this->data[$table]);
            if (count($keys) === 0) {
                $id = 1;
            } else {
                $id = max($keys) + 1;
            }
        }

        $filtered['id'] = $id;
        $this->data[$table][$id] = $filtered;

        return $id;
    }

    public function saveRows(string $table, array $columns, array $data): array
    {
        if (! array_key_exists(0, $data)) {
            return [$this->saveRow($table, $columns, $data)];
        }

        $ids = [];
        foreach ($data as $row) {
            $ids[] = $this->saveRow($table, $columns, $row);
        }

        return $ids;
    }
}
