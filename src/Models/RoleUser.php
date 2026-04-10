<?php

namespace Dsewth\SimpleHRBAC\Models;

use Dsewth\SimpleHRBAC\Observers\RoleUserObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[ObservedBy(RoleUserObserver::class)]
class RoleUser extends Pivot
{
    public function invalidateUserCache(): void
    {
        if (! app()->bound('rbac.service')) {
            return;
        }

        try {
            app('rbac.service')->invalidateUserCache($this->user_id);
        } catch (\Exception $e) {
        }
    }
}
