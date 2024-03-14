<?php

namespace Dsewth\SimpleHRBAC\Models;

use Dsewth\SimpleHRBAC\Exceptions\RBACException;
use Dsewth\SimpleHRBAC\RBAC;

class BaseModel
{
    protected static string $table = '';

    public static function fromRow(array $row): BaseModel
    {
        throw new RBACException('fromRow must be reimplemented in the child class');
    }

    public static function fromData(array $data)
    {
        return static::fromRow($data);
    }

    public static function find($id): ?BaseModel
    {
        if (empty(static::$table)) {
            throw new RBACException('$table should be set to the model table name');
        }

        $result = RBAC::getInstance()->database()->select(static::$table, ['id' => $id]);

        if (empty($result)) {
            return null;
        }

        return static::fromRow($result[0]);
    }

    public static function select(array $filter = ['*']): array
    {
        if (empty(static::$table)) {
            throw new RBACException('$table should be set to the model table name');
        }

        $result = RBAC::getInstance()->database()->select(static::$table, $filter);

        return array_map(function ($row) {
            return static::fromRow($row);
        }, $result);
    }

    public static function all(): array
    {
        return static::select();
    }
}
