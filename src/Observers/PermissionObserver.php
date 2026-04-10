<?php

namespace Dsewth\SimpleHRBAC\Observers;

use Dsewth\SimpleHRBAC\Models\Permission;

class PermissionObserver
{
    public function updating(Permission $permission): void
    {
        if ($permission->isDirty('name') && $permission->getOriginal('name') !== $permission->name) {
            $this->invalidateCache($permission->getOriginal('name'));
        }
    }

    public function deleted(Permission $permission): void
    {
        $this->invalidateCache($permission->name);
    }

    private function invalidateCache(string $permissionName): void
    {
        if (! app()->bound('rbac.service')) {
            return;
        }

        try {
            app('rbac.service')->invalidatePermissionCache($permissionName);
        } catch (\Exception $e) {
        }
    }
}
