<?php

namespace Dsewth\SimpleHRBAC\Database;

use Dsewth\SimpleHRBAC\Exceptions\RBACException;

/**
 * @method array permissions(array $filter = ['*'])
 * @method array roles(array $filter = ['*'])
 * @method array subjects(array $filter = ['*'])
 * @method void savePermissions(array $permissions)
 * @method void saveRoles(array $roles)
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
}
