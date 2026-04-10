<?php

use Dsewth\SimpleHRBAC\Facades\RBAC;
use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Models\Role;
use Illuminate\Support\Facades\Cache;
use Workbench\App\Models\User;

require_once 'Constants.php';

beforeAll(function () {
    DataHelper::useUserModel(User::class);
});

beforeEach(function () {
    Cache::flush();
});

test('Attaching role triggers pivot boot event', function () {
    DataHelper::importJsonFile(DATASET);

    $user = User::where('name', 'Bob')->first();
    $role = Role::find(3);
    $permission = 'Print';

    $user->roles()->detach();
    expect(RBAC::can($user->id, $permission))->toBeFalse();

    $user->roles()->attach($role->id);

    expect(RBAC::can($user->id, $permission))->toBeTrue();
});

test('Detaching role triggers pivot boot event', function () {
    DataHelper::importJsonFile(DATASET);

    $user = User::where('name', 'Bob')->first();
    $role = Role::find(3);
    $permission = 'Print';

    $user->roles()->attach($role->id);
    expect(RBAC::can($user->id, $permission))->toBeTrue();

    $user->roles()->detach($role->id);

    expect(RBAC::can($user->id, $permission))->toBeFalse();
});

test('Syncing roles triggers pivot boot events', function () {
    DataHelper::importJsonFile(DATASET);

    $user = User::where('name', 'Bob')->first();
    $permission = 'Print';

    $user->roles()->sync([3]);
    expect(RBAC::can($user->id, $permission))->toBeTrue();

    $user->roles()->sync([]);
    expect(RBAC::can($user->id, $permission))->toBeFalse();
});
