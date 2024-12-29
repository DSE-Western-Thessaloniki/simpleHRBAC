<?php

namespace Dsewth\SimpleHRBAC\Facades;

use Dsewth\SimpleHRBAC\Services\RBACService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool importJsonFile(string $filename)
 * @method static bool importData(array $data)
 * @method static \Illuminate\Support\Collection<\Dsewth\SimpleHRBAC\Models\Permission> getPermissionsOf($user)
 * @method static bool can(int $userId, string $permission)
 */
class RBAC extends Facade
{
    protected static function getFacadeAccessor()
    {
        return RBACService::class;
    }
}
