<?php

use Dsewth\SimpleHRBAC\Exceptions\RBACException;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Dsewth\SimpleHRBAC\Models\Subject;
use Dsewth\SimpleHRBAC\RBAC;
use Illuminate\Support\Collection;

test('RBAC can be initialized with data', function () {
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
        ->toBe(6);

    $subjects = Subject::all();
    expect($subjects->count())
        ->toBe(4);

    expect(Permission::find(1)->roles)->toHaveCount(2);
    expect(Role::find(1)->permissions)->toHaveCount(0);
    expect(Role::find(2)->permissions)->toHaveCount(3);
    expect(Role::find(1)->subjects)->toHaveCount(1);
});

test('RBAC can get children of a role', function () {
    /** @var RBAC $rbac */
    $rbac = app(RBAC::class);
    $rbac->loadJsonFile(__DIR__.'/../../Data/Json/Dataset.json');

    /** @var Role $role */
    $role = Role::find(1);
    $children = $role->children();
    expect($children)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(5)
        ->toContainOnlyInstancesOf(Role::class);
    expect($children->where('id', 1))->toHaveCount(0);
});

test('RBAC can get the immediate parent of a role', function () {
    /** @var RBAC $rbac */
    $rbac = app(RBAC::class);
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
    /** @var RBAC $rbac */
    $rbac = app(RBAC::class);
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
    /** @var RBAC $rbac */
    $rbac = app(RBAC::class);
    $rbac->loadJsonFile(__DIR__.'/../../Data/Json/Dataset.json');

    /** @var Role $role */
    $role = Role::find(6);
    $role->delete();
    expect(Role::find(6))->toBeNull();
    expect(Role::find(1)->children())
        ->toHaveCount(4);
    expect(Role::find(1)->children()->where('id', 6))->toHaveCount(0);
});

test('RBAC cannot delete the root of the role tree', function () {
    /** @var RBAC $rbac */
    $rbac = app(RBAC::class);
    $rbac->loadJsonFile(__DIR__.'/../../Data/Json/Dataset.json');

    /** @var Role $role */
    $role = Role::find(1);
    expect(fn () => $role->delete())->toThrow(RBACException::class);
    expect(Role::find(1))->not->toBeNull();
});

test('RBAC can delete a role from the middle of the tree', function () {
    /** @var RBAC $rbac */
    $rbac = app(RBAC::class);
    $rbac->loadJsonFile(__DIR__.'/../../Data/Json/Dataset.json');

    /** @var Role $role */
    $role = Role::find(3);
    $role->delete();
    expect(Role::all())->toHaveCount(5);
    expect(Role::all()->whereIn('id', [1, 2, 4, 5, 6]))->toHaveCount(5);
    expect(Role::find(1)->children())->toHaveCount(4);
});

test('RBAC can move a role from the middle of the tree', function () {
    /** @var RBAC $rbac */
    $rbac = app(RBAC::class);
    $rbac->loadJsonFile(__DIR__.'/../../Data/Json/Dataset.json');

    /** @var Role $role */
    $role = Role::find(3);
    $role->parent_id = 2;
    $role->save();
    expect(Role::all())->toHaveCount(6);
    expect(Role::find(4)->parents())->toHaveCount(3);
});

test('RBAC cannot move the root role', function () {
    /** @var RBAC $rbac */
    $rbac = app(RBAC::class);

    /** @var Role $root */
    $root = Role::create([
        'name' => 'Root',
        'description' => 'Root',
    ]);

    /** @var Role $root */
    $role = Role::create([
        'name' => 'Second root',
        'description' => 'Second root',
        'parent_id' => $root->id,
    ]);

    $root->parent_id = $role->id;
    expect(fn () => $root->save())->toThrow(RBACException::class);
    expect(Role::all())->toHaveCount(2);
    expect(Role::find(1)->parent_id)->toBeNull();
});

test('RBAC can have only one root node', function () {
    /** @var RBAC $rbac */
    $rbac = app(RBAC::class);

    /** @var Role $role */
    $root = Role::create([
        'name' => 'Root',
        'description' => 'Root',
    ]);

    expect(fn () => Role::create(['name' => 'Second root']))->toThrow(RBACException::class);
    expect(Role::all())->toHaveCount(1);

    /** @var Role $role */
    $role = Role::create([
        'name' => 'Second root',
        'description' => 'Second root',
        'parent_id' => $root->id,
    ]);
    $role->parent_id = null;
    expect(fn () => $role->save())->toThrow(RBACException::class);
    expect(Role::find(2)->parent_id)->not->toBeNull();
    expect(Role::all())->toHaveCount(2);
});

test('RBAC nodes cannot be their own parents', function () {
    /** @var RBAC $rbac */
    $rbac = app(RBAC::class);

    expect(fn () => Role::create([
        'name' => 'Root',
        'description' => 'Root',
        'parent_id' => 1,
    ]))->toThrow(RBACException::class);
    expect(Role::all())->toHaveCount(0);

    // Δοκιμή ενημέρωσης κόμβου
    /** @var Role $root */
    $root = Role::create([
        'name' => 'Root',
        'description' => 'Root',
    ]);

    /** @var Role $role */
    $role = Role::create([
        'name' => 'Role',
        'parent_id' => $root->id,
    ]);

    $role->parent_id = $role->id;
    expect(fn () => $role->save())->toThrow(RBACException::class);
});

test('RBAC can update a role info', function () {
    /** @var RBAC $rbac */
    $rbac = app(RBAC::class);

    /** @var Role $role */
    $role = Role::create([
        'name' => 'Root',
        'description' => 'Root',
    ]);

    $role->name = 'New name';
    $role->description = 'New description';
    $role->save();

    expect(Role::find($role->id)->name)->toBe('New name');
    expect(Role::find($role->id)->description)->toBe('New description');
});

test('RBAC can get the permissions of a subject', function () {
    /** @var RBAC $rbac */
    $rbac = app(RBAC::class);
    $rbac->loadJsonFile(__DIR__.'/../../Data/Json/Dataset.json');

    $permissions = $rbac->getPermissionsOf(Subject::find(4));
    expect($permissions)
        ->toHaveCount(3)
        ->toContainOnlyInstancesOf(Permission::class);
    expect($permissions->where('id', 1)->first())->not->toBeNull();
    expect($permissions->where('id', 2)->first())->not->toBeNull();
    expect($permissions->where('id', 3)->first())->not->toBeNull();
});

test('RBAC can check if a subject has a permission', function () {
    /** @var RBAC $rbac */
    $rbac = app(RBAC::class);
    $rbac->loadJsonFile(__DIR__.'/../../Data/Json/Dataset.json');

    expect($rbac->can(Subject::find(1), Permission::find(1)))->toBeFalse();
    expect($rbac->can(Subject::find(4), Permission::find(1)))->toBeTrue();
});
