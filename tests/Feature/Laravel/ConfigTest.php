<?php

use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Providers\SimpleHRBACServiceProvider;
use Illuminate\Support\Facades\Config;

test('RBAC should ignore the user_model class if it is non-existent', function () {
    Config::set('simple-hrbac.user_model', 'InvalidClass');
    $provider = new SimpleHRBACServiceProvider($this->app);
    $provider->setUserModel();
    expect(DataHelper::getUserModelClass())
        ->toBe(\App\Models\User::class);
});

test('DataHelper should automatically get the user_model class', function () {
    expect(DataHelper::getUserModelClass())
        ->toBe(\App\Models\User::class);

    // Άλλαξε την τιμή και φόρτωσε εκ νέου την υπηρεσία
    Config::set('simple-hrbac.user_model', \Workbench\App\Models\User::class);
    $provider = new SimpleHRBACServiceProvider($this->app);
    $provider->setUserModel();
    expect(DataHelper::getUserModelClass())
        ->toBe(\Workbench\App\Models\User::class);
});
