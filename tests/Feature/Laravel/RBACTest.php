<?php

use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Dsewth\SimpleHRBAC\Models\Subject;
use Dsewth\SimpleHRBAC\RBAC;
use Illuminate\Support\Facades\DB;

test('RBAC can be initialized with data', function () {
    // dd(DB::getConfig());
    expect(file_exists(__DIR__.'/../../Data/Json/Dataset.json'))->toBeTrue();

    /** @var RBAC $rbac */
    $rbac = app(RBAC::class);
    expect($rbac)->toBeInstanceOf(RBAC::class);

    $rbac->loadJsonFile(__DIR__.'/../../Data/Json/Dataset.json');

    $permissions = Permission::all();
    expect($permissions->count())
        ->toBe(3);

    $roles = Role::all();
    expect($roles->count())
        ->toBe(4);

    $subjects = Subject::all();
    expect($subjects->count())
        ->toBe(4);
});
