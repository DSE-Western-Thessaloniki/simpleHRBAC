<?php

namespace Dsewth\SimpleHRBAC\Database;

use Dsewth\SimpleHRBAC\Exceptions\RBACException;

/**
 * @method array select(string $table, array $filter = ['*'])
 * @method array saveRows(string $table, array $columns, array $rows)
 */
class Database
{
    private DriverInterface $driver;

    public function __construct(DriverInterface $driver)
    {
        $this->driver = $driver;
    }

    public function __call(string $name, array $arguments)
    {
        if (is_callable([$this->driver, $name])) {
            return call_user_func_array([$this->driver, $name], $arguments);
        }

        throw new RBACException("Invalid function '$name' called for '{$this->driver->driverName()}'");
    }

    public function driver()
    {
        return $this->driver;
    }
}
