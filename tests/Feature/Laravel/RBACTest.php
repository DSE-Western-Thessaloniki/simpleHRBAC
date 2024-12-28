<?php

use Dsewth\SimpleHRBAC\Exceptions\RBACException;
use Dsewth\SimpleHRBAC\Facades\RBAC;
use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Workbench\App\Models\User;

require_once 'Constants.php';

beforeAll(function () {
    DataHelper::useUserModel(User::class);
});

test('RBAC can be initialized with data', function () {
    expect(file_exists(DATASET))->toBeTrue();

    DataHelper::importJsonFile(DATASET);

    $permissions = Permission::all();
    expect($permissions->count())
        ->toBe(3);

    $roles = Role::all();
    expect($roles->count())
        ->toBe(6);

    $users = User::all();
    expect($users->count())
        ->toBe(4);

    expect(Permission::find(1)->roles)->toHaveCount(2);
    expect(Role::find(1)->permissions)->toHaveCount(0);
    expect(Role::find(2)->permissions)->toHaveCount(3);
    expect(Role::find(1)->users)->toHaveCount(1);
});

test('RBAC can get children of a role', function () {
    DataHelper::importJsonFile(DATASET);

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
    DataHelper::importJsonFile(DATASET);

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
    DataHelper::importJsonFile(DATASET);

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
    DataHelper::importJsonFile(DATASET);

    /** @var Role $role */
    $role = Role::find(6);
    $role->delete();
    expect(Role::find(6))->toBeNull();
    expect(Role::find(1)->children())
        ->toHaveCount(4);
    expect(Role::find(1)->children()->where('id', 6))->toHaveCount(0);
    expect(DB::table('role_tree')
        ->where('parent', 6)
        ->orWhere('child', 6)
        ->get())
        ->toBeEmpty();
    expect(DB::table('role_user')
        ->where('role_id', 6)
        ->get())
        ->toBeEmpty();
    expect(DB::table('permission_role')
        ->where('role_id', 6)
        ->get())
        ->toBeEmpty();
});

test('RBAC cannot delete the root of the role tree', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $role */
    $role = Role::find(1);
    expect(fn () => $role->delete())->toThrow(RBACException::class);
    expect(Role::find(1))->not->toBeNull();
});

test('RBAC can delete a role from the middle of the tree', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $role */
    $role = Role::find(3);
    $role->delete();
    expect(Role::all())->toHaveCount(5);
    expect(Role::all()->whereIn('id', [1, 2, 4, 5, 6]))->toHaveCount(5);
    expect(Role::find(4)->parent_id)->toBe(1);
    expect(Role::find(1)->children())->toHaveCount(4);
    expect(DB::table('role_tree')
        ->where('parent', 3)
        ->orWhere('child', 3)
        ->get())
        ->toBeEmpty();
    expect(DB::table('role_tree')
        ->select('depth')
        ->where('child', 4)
        ->whereNot('parent', 4)
        ->first()
        ->depth)
        ->toBe(1);
    expect(DB::table('role_user')
        ->where('role_id', 3)
        ->get())
        ->toBeEmpty();
    expect(DB::table('permission_role')
        ->where('role_id', 3)
        ->get())
        ->toBeEmpty();
});

test('RBAC can move a role from the middle of the tree', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $role */
    $role = Role::find(3);
    $role->parent_id = 2;
    $role->save();
    expect(Role::all())->toHaveCount(6);
    expect(Role::find(4)->parents())->toHaveCount(3);
});

test('RBAC cannot move the root role', function () {
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

test('RBAC can get the permissions of a user', function () {
    DataHelper::importJsonFile(DATASET);

    $permissions = RBAC::getPermissionsOf(User::find(4));
    expect($permissions)
        ->toHaveCount(3)
        ->toContainOnlyInstancesOf(Permission::class);
    expect($permissions->where('id', 1)->first())->not->toBeNull();
    expect($permissions->where('id', 2)->first())->not->toBeNull();
    expect($permissions->where('id', 3)->first())->not->toBeNull();
});

test('RBAC can check if a user has a permission', function () {
    DataHelper::importJsonFile(DATASET);

    $user = User::where('name', 'Bob')->first();
    expect(RBAC::can($user->id, 'Print'))->toBeFalse();

    $user2 = User::where('name', 'root')->first();
    expect(RBAC::can($user2->id, 'Print'))->toBeTrue();
});

test('RBAC uses once() to avoid querying the database again', function () {
    DataHelper::importJsonFile(DATASET);

    $user = User::where('name', 'Bob')->first();
    expect($user->canUsingRBAC('Print'))->toBeFalse();
    DB::enableQueryLog();
    expect($user->canUsingRBAC('Print'))->toBeFalse();
    expect(DB::getQueryLog())->toBeEmpty();

    $user2 = User::where('name', 'root')->first();
    expect($user2->canUsingRBAC('Print'))->toBeTrue();
    expect(DB::getQueryLog())->not->toBeEmpty();
    DB::flushQueryLog();
    expect($user2->canUsingRBAC('Print'))->toBeTrue();
    expect(DB::getQueryLog())->toBeEmpty();
});

test("RBAC returns false if permission doesn't exist", function () {
    $user = User::factory()->create();
    $user->roles()->attach(Role::factory()->create());
    expect(RBAC::can($user->id, 'NonexistentPermission'))->toBeFalse();

    $permission = Permission::factory()->create(['name' => 'NonexistentPermission']);
    // Πρέπει να επανεκκινήσουμε την εφαρμογή για να διαγράψουμε τα αποτελέσματα
    // του once(). Φυσικά δε θέλουμε να ξαναχτίσουμε την βάση από την αρχή.
    $this->refreshApplication();
    $this->restoreInMemoryDatabase();
    expect(RBAC::can($user->id, 'NonexistentPermission'))->toBeFalse();

    $user->roles()->first()->permissions()->attach($permission);
    $this->refreshApplication();
    $this->restoreInMemoryDatabase();
    expect(RBAC::can($user->id, 'NonexistentPermission'))->toBeTrue();
});

test('RBAC throws an exception when trying to get permissions of a non-existent user', function () {
    expect(fn () => RBAC::getPermissionsOf(User::find(1)))->toThrow(InvalidArgumentException::class);
});

test('RBAC throws an exception when trying to get permissions of an invalid model', function () {
    // Δεν έχει το HasRoles trait
    expect(fn () => RBAC::getPermissionsOf(\Orchestra\Testbench\Factories\UserFactory::new()))->toThrow(InvalidArgumentException::class);

    // Δεν είναι model
    expect(fn () => RBAC::getPermissionsOf(Role::factory()->create()))->toThrow(InvalidArgumentException::class);
});

test('RBAC can return users that have specific permission', function () {
    DataHelper::importJsonFile(DATASET);

    $users = RBAC::getUsersWithPermission('Print');
    expect($users)->toHaveCount(3);
    expect($users->contains('id', 2))->toBeTrue();
    expect($users->contains('id', 3))->toBeTrue();
    expect($users->contains('id', 4))->toBeTrue();
});
