<?php

namespace Dsewth\SimpleHRBAC\Services;

use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Traits\HasRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RBACService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge(config('simple-hrbac', []), $config);
    }

    /**
     * Επέστρεψε μια συλλογή των δικαιωμάτων ενός χρήστη
     *
     * @return Illuminate\Support\Collection<Permission>
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
        return Permission::whereHas('roles', function ($q) use ($allRoleIds) {
            $q->whereIn('role_id', $allRoleIds);
        })->get();
    }

    /**
     * Επέστρεψε true αν ένας χρήστης έχει το δικαίωμα, αλλιώς false
     *
     * @param  int  $userId  Αναγνωριστικό χρήστη
     * @param  string  $permission  Όνομα δικαιώματος
     */
    public function can(int $userId, string $permission): bool
    {
        return once(function () use ($userId, $permission) {
            if (Permission::where('name', $permission)->exists()) {
                $userModelClass = DataHelper::getUserModelClass();
                $userPermissions = $this->getPermissionsOf($userModelClass::find($userId));

                return $userPermissions->contains('name', $permission);
            }

            return false;
        });
    }

    public function getUsersWithPermission(string $permission): Collection
    {
        $permissions = Permission::where('name', $permission)->first();

        if (! $permissions) {
            return collect();
        }

        return $permissions
            ->roles
            ->map(function ($role) {
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
