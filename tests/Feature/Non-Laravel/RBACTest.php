<?php

use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Dsewth\SimpleHRBAC\Models\Subject;
use Dsewth\SimpleHRBAC\RBAC;
use Illuminate\Support\Collection;

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

test('RBAC can get children of a role', function () {
    $rbac = new RBAC([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    $rbac->loadJsonFile(__DIR__.'/../../Data/Json/Dataset.json');

    /** @var Role $role */
    $role = Role::find(1);
    $children = $role->children();
    expect($children)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(3)
        ->toContainOnlyInstancesOf(Role::class);
    expect($children->where('id', 1))->toHaveCount(0);
});

test('RBAC can get the immediate parent of a role', function () {
    $rbac = new RBAC([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    $rbac->loadJsonFile(__DIR__.'/../../Data/Json/Dataset.json');

    /** @var Role $role */
    $role = Role::find(4);
    $parent = $role->parent();
    expect($parent)->toBeInstanceOf(Role::class)
        ->toHaveKey('id', 3);

    $role = Role::find(1);
    $parent = $role->parent();
    expect($parent)->toBeNull();
});

test('RBAC can get parents of a role', function () {
    $rbac = new RBAC([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    $rbac->loadJsonFile(__DIR__.'/../../Data/Json/Dataset.json');

    /** @var Role $role */
    $role = Role::find(4);
    $parents = $role->parents();
    expect($parents)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(2)
        ->toContainOnlyInstancesOf(Role::class);
    expect($parents->where('id', 4))->toHaveCount(0);
});

test('RBAC can delete a leaf of the role tree', function () {
    $rbac = new RBAC([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    $rbac->loadJsonFile(__DIR__.'/../../Data/Json/Dataset.json');

    /** @var Role $role */
    $role = Role::find(4);
    $role->delete();
    expect(Role::find(4))->toBeNull();
    expect(Role::find(1)->children())
        ->toHaveCount(2);
    expect(Role::find(1)->children()->where('id', 4))->toHaveCount(0);
});

test('RBAC can delete the root of the role tree', function () {
    $rbac = new RBAC([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    $rbac->loadJsonFile(__DIR__.'/../../Data/Json/Dataset.json');

    /** @var Role $role */
    $role = Role::find(1);
    $role->delete();
    expect(Role::all())->toHaveCount(0);
});

test('RBAC can delete a role from the middle of the tree', function () {
    $rbac = new RBAC([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    $rbac->loadJsonFile(__DIR__.'/../../Data/Json/Dataset.json');

    /** @var Role $role */
    $role = Role::find(3);
    $role->delete();
    expect(Role::all())->toHaveCount(2);
    expect(Role::all()->whereIn('id', [1, 2]))->toHaveCount(2);
    expect(Role::find(1)->children())->toHaveCount(1);
});

test('RBAC can move a role from the middle of the tree', function () {
    $rbac = new RBAC([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    $rbac->loadJsonFile(__DIR__.'/../../Data/Json/Dataset.json');

    /** @var Role $role */
    $role = Role::find(3);
    $role->parent_id = 2;
    $role->save();
    expect(Role::all())->toHaveCount(4);
    expect(Role::find(4)->parents())->toHaveCount(3);
});
