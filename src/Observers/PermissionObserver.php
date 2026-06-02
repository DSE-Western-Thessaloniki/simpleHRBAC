<?php

namespace Dsewth\SimpleHRBAC\Observers;

use Dsewth\SimpleHRBAC\Helpers\PermissionWildcard;
use Dsewth\SimpleHRBAC\Models\Permission;
use Illuminate\Support\Facades\DB;

class PermissionObserver
{
    public function updating(Permission $permission): void
    {
        if ($permission->isDirty('name') && $permission->getOriginal('name') !== $permission->name) {
            $originalName = $permission->getOriginal('name');
            $this->invalidatePermissionCache($originalName);

            // If either the old or the new name is a wildcard, cached can()
            // entries for the names it covered are tagged by their literal
            // queried name and won't be picked up by the permission-name tag
            // flush above. Invalidate the affected users explicitly.
            if (PermissionWildcard::isPattern($originalName) || PermissionWildcard::isPattern($permission->name)) {
                foreach ($this->collectAffectedUserIds($permission->id) as $userId) {
                    $this->invalidateUserCache($userId);
                }
            }
        }
    }

    public function deleting(Permission $permission): void
    {
        // For wildcards, flush the affected users' caches before the DELETE
        // happens so the pivot rows we walk are still present. Doing this in
        // `deleted` would be too late (and observer instances are not
        // guaranteed to be reused between events, so snapshotting state is
        // unreliable).
        if (PermissionWildcard::isPattern($permission->name)) {
            foreach ($this->collectAffectedUserIds($permission->id) as $userId) {
                $this->invalidateUserCache($userId);
            }
        }
    }

    public function deleted(Permission $permission): void
    {
        $this->invalidatePermissionCache($permission->name);
    }

    /**
     * @return array<int, int>
     */
    private function collectAffectedUserIds(int $permissionId): array
    {
        $directRoleIds = DB::table('permission_role')
            ->where('permission_id', $permissionId)
            ->pluck('role_id');

        if ($directRoleIds->isEmpty()) {
            return [];
        }

        $descendantRoleIds = DB::table('role_tree')
            ->whereIn('parent', $directRoleIds)
            ->pluck('child');

        $allRoleIds = $directRoleIds->merge($descendantRoleIds)->unique();

        return DB::table('role_user')
            ->whereIn('role_id', $allRoleIds)
            ->pluck('user_id')
            ->unique()
            ->all();
    }

    private function invalidatePermissionCache(string $permissionName): void
    {
        if (! app()->bound('rbac.service')) {
            return;
        }

        try {
            app('rbac.service')->invalidatePermissionCache($permissionName);
        } catch (\Exception $e) {
        }
    }

    private function invalidateUserCache(int $userId): void
    {
        if (! app()->bound('rbac.service')) {
            return;
        }

        try {
            app('rbac.service')->invalidateUserCache($userId);
        } catch (\Exception $e) {
        }
    }
}
