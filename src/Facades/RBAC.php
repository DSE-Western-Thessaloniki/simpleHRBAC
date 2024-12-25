<?php

namespace Dsewth\SimpleHRBAC\Facades;

use Dsewth\SimpleHRBAC\Services\RBACService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool importJsonFile(string $filename)
 * @method static bool importData(array $data)
 * @method static \Dsewth\SimpleHRBAC\Collection<Permission> getPermissionsOf($user)
 * @method static bool can(int $userId, string $permission)
 */
class RBAC extends Facade
{
    protected static function getFacadeAccessor()
    {
        return RBACService::class;
    }
}
