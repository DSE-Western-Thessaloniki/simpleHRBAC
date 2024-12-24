<?php

namespace Dsewth\SimpleHRBAC\Facades;

use Dsewth\SimpleHRBAC\Services\RBACService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool importJsonFile(string $filename)
 * @method static bool importData(array $data)
 * @method static \Dsewth\SimpleHRBAC\Collection<Permission> getPermissionsOf(Subject $subject)
 * @method static bool can(int $subjectId, string $permission)
 */
class RBAC extends Facade
{
    protected static function getFacadeAccessor()
    {
        return RBACService::class;
    }
}
