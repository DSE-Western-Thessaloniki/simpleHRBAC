<?php

use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Dsewth\SimpleHRBAC\Models\Subject;
use Dsewth\SimpleHRBAC\RBAC;

test('RBAC can be initialized', function () {
    $rbac = new RBAC([
        'database' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ],
    ]);
    expect($rbac)->toBeInstanceOf(RBAC::class);
});

test('RBAC can be initialized with data', function () {
    expect(file_exists(__DIR__.'/../../Data/Json/Dataset.json'))->toBeTrue();

    $rbac = new RBAC([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $rbac->loadJsonFile(__DIR__.'/../../Data/Json/Dataset.json');

    $permissions = Permission::all();
    expect(count($permissions))
        ->toBe(3);

    $roles = Role::all();
    expect(count($roles))
        ->toBe(4);

    $subjects = Subject::all();
    expect(count($subjects))
        ->toBe(4);
});
