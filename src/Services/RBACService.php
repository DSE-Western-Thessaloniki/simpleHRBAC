<?php

namespace Dsewth\SimpleHRBAC\Services;

use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Traits\HasRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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
     * @return Dsewth\SimpleHRBAC\Collection<Permission>
     */
    public function getPermissionsOf($user): Collection
    {
        if (! ($user instanceof Model) || ! in_array(HasRoles::class, class_uses($user))) {
            throw new \InvalidArgumentException('Invalid user model. Expected to implement HasRoles trait. Also, ensure the user model extends the base model.');
        }

        $permissions = new Collection;
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $permissions->push($permission);
            }

            // Πρέπει να ελέγξουμε και τα παιδιά του ρόλου
            foreach ($role->children() as $child) {
                foreach ($child->permissions as $permission) {
                    $permissions->push($permission);
                }
            }
        }

        return $permissions->unique('id');
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
            throw new \InvalidArgumentException('Permission not found.');
        }

        return $permissions
            ->roles
            ->map(function ($role) {
                $roles = collect();
                $currentRole = $role;
                while ($currentRole->parent_id !== null) {
                    $roles->push($currentRole);
                    $currentRole = $currentRole->parent();
                }

                $roles->push($currentRole);

                return $roles;
            })
            ->flatten()
            ->unique('id')
            ->flatMap(function ($role) {
                return $role->users;
            })
            ->unique('id');
    }
}
