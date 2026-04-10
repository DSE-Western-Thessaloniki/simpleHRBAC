<?php

namespace Dsewth\SimpleHRBAC\Observers;

use Dsewth\SimpleHRBAC\Models\RoleUser;

class RoleUserObserver
{
    public function created(RoleUser $pivot): void
    {
        $pivot->invalidateUserCache();
    }

    public function deleted(RoleUser $pivot): void
    {
        $pivot->invalidateUserCache();
    }
}
