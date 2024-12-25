<?php

use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Providers\SimpleHRBACServiceProvider;
use Illuminate\Support\Facades\Config;

test('DataHelper should automatically get the user_model class', function () {
    Config::set('simple-hrbac.user_model', \Workbench\App\Models\User::class);
    $provider = new SimpleHRBACServiceProvider($this->app);
    $provider->setUserModel();
    expect(DataHelper::getUserModelClass())
        ->toBe(\Workbench\App\Models\User::class);
});
