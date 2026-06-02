<?php

namespace Dsewth\SimpleHRBAC\Services;

use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Helpers\PermissionWildcard;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Dsewth\SimpleHRBAC\Traits\HasRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RBACService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge(config('simple-hrbac', []), $config);
    }

    private static function cacheKey(int $userId, string $permission): string
    {
        return "rbac:can:{$userId}:{$permission}";
    }

    private static function userCacheTag(int $userId): string
    {
        return "rbac:user:{$userId}";
    }

    private static function permissionCacheTag(string $permission): string
    {
        return "rbac:permission:{$permission}";
    }

    /**
     * Επέστρεψε μια συλλογή των δικαιωμάτων ενός χρήστη
     *
     * @param  Model  $user  Ο χρήστης για τον οποίο θέλουμε να πάρουμε τα δικαιώματα
     * @return Collection<Permission>
     */
    public function getPermissionsOf($user): Collection
    {
        if (! ($user instanceof Model) || ! in_array(HasRoles::class, class_uses($user))) {
            throw new \InvalidArgumentException('Invalid user model. Expected to implement HasRoles trait. Also, ensure the user model extends the base model.');
        }

        $roleIds = $user->roles()->pluck('roles.id');

        if ($roleIds->isEmpty()) {
            return collect();
        }

        // Βρες όλους τους ρόλους των απογόνων
        $allRoleIds = DB::table('role_tree')
            ->whereIn('parent', $roleIds)
            ->pluck('child')
            ->unique()
            ->merge($roleIds);

        // Βρες όλα τα δικαιώματα με μια ερώτηση
        $permissions = Permission::whereHas('roles', function ($q) use ($allRoleIds) {
            $q->whereIn('role_id', $allRoleIds);
        })->get();

        return PermissionWildcard::simplify($permissions);
    }

    /**
     * Επέστρεψε true αν ένας χρήστης έχει το δικαίωμα, αλλιώς false
     *
     * @param  int  $userId  Αναγνωριστικό χρήστη
     * @param  string  $permission  Όνομα δικαιώματος
     */
    public function can(int $userId, string $permission): bool
    {
        $cacheKey = self::cacheKey($userId, $permission);
        $userTag = self::userCacheTag($userId);
        $permissionTag = self::permissionCacheTag($permission);

        try {
            return Cache::tags([$userTag, $permissionTag])->rememberForever($cacheKey, function () use ($userId, $permission) {
                return $this->canWithoutCache($userId, $permission);
            });
        } catch (\Exception $e) {
            return $this->canWithoutCache($userId, $permission);
        }
    }

    protected function canWithoutCache(int $userId, string $permission): bool
    {
        $userModelClass = DataHelper::getUserModelClass();
        $user = $userModelClass::find($userId);

        if (! $user) {
            return false;
        }

        $userPermissions = $this->getPermissionsOf($user);

        return $userPermissions->contains(
            fn (Permission $p) => PermissionWildcard::matches($p->name, $permission)
        );
    }

    public function invalidateUserCache(int $userId): void
    {
        try {
            Cache::tags([self::userCacheTag($userId)])->flush();
        } catch (\Exception $e) {
        }
    }

    public function invalidatePermissionCache(string $permission): void
    {
        try {
            Cache::tags([self::permissionCacheTag($permission)])->flush();

            // Cached can() entries are tagged by the literal queried name. When
            // a wildcard permission like `view.*` changes, queried entries
            // such as `view.1` (tagged only under `view.1`) would survive that
            // flush. Invalidate the affected users to drop them too.
            if (PermissionWildcard::isPattern($permission)) {
                $this->invalidateUsersHoldingPermission($permission);
            }
        } catch (\Exception $e) {
        }
    }

    /**
     * Invalidate the per-user can() cache for every user that holds the named
     * permission either directly or through a role they inherit from.
     */
    public function invalidateUsersHoldingPermission(string $permissionName): void
    {
        $permission = Permission::where('name', $permissionName)->first();
        if (! $permission) {
            return;
        }

        $directRoleIds = DB::table('permission_role')
            ->where('permission_id', $permission->id)
            ->pluck('role_id');

        if ($directRoleIds->isEmpty()) {
            return;
        }

        $descendantRoleIds = DB::table('role_tree')
            ->whereIn('parent', $directRoleIds)
            ->pluck('child');

        $allRoleIds = $directRoleIds->merge($descendantRoleIds)->unique();

        $userIds = DB::table('role_user')
            ->whereIn('role_id', $allRoleIds)
            ->pluck('user_id')
            ->unique();

        foreach ($userIds as $userId) {
            $this->invalidateUserCache($userId);
        }
    }

    public function invalidateAllCache(): void
    {
        try {
            $permissionNames = Permission::pluck('name');
            $allTags = [];

            foreach ($permissionNames as $permName) {
                $allTags[] = self::permissionCacheTag($permName);
            }

            $userModelClass = DataHelper::getUserModelClass();
            $userIds = $userModelClass::query()->pluck('id');
            foreach ($userIds as $userId) {
                $allTags[] = self::userCacheTag($userId);
            }

            if (! empty($allTags)) {
                Cache::tags($allTags)->flush();
            }
        } catch (\Exception $e) {
        }
    }

    public function getUsersWithPermission(string $permission): Collection
    {
        // A stored permission whose name is a wildcard pattern (e.g. `view.*`)
        // grants every name it matches, so we have to consider every stored
        // permission, not just the literal match.
        $matchingPermissions = Permission::with('roles')
            ->get()
            ->filter(fn (Permission $p) => PermissionWildcard::matches($p->name, $permission));

        if ($matchingPermissions->isEmpty()) {
            return collect();
        }

        return $matchingPermissions
            ->flatMap(fn (Permission $p) => $p->roles)
            ->unique('id')
            ->map(function (Role $role) {
                return $role->parents()->push($role);
            })
            ->flatten()
            ->unique('id')
            ->flatMap(function ($role) {
                return $role->users;
            })
            ->unique('id');
    }
}
