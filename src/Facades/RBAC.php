<?php

namespace Dsewth\SimpleHRBAC\Facades;

use Dsewth\SimpleHRBAC\Services\RBACService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection<\Dsewth\SimpleHRBAC\Models\Permission> getPermissionsOf(\Illuminate\Database\Eloquent\Model $user)
 * @method static bool can(int $userId, string $permission)
 * @method static void invalidateUserCache(int $userId)
 * @method static void invalidatePermissionCache(string $permission)
 * @method static void invalidateAllCache()
 * @method static \Illuminate\Support\Collection<\Illuminate\Database\Eloquent\Model> getUsersWithPermission(string $permission)
 */
class RBAC extends Facade
{
    protected static function getFacadeAccessor()
    {
        return RBACService::class;
    }
}
