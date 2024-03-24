<?php

use Dsewth\SimpleHRBAC\Database\Driver\ArrayDriver;
use Dsewth\SimpleHRBAC\Exceptions\RBACException;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Dsewth\SimpleHRBAC\Models\Subject;
use Dsewth\SimpleHRBAC\RBAC;

beforeEach(function () {
    try {
        RBAC::destroy();
    } catch (RBACException $e) { // Ignore exception
    }
});

test('RBAC can be initialized with ArrayDriver', function () {
    $rbac = RBAC::initialize(new ArrayDriver());
    expect($rbac)->toBeInstanceOf(RBAC::class);
    unset($rbac);
});

test('ArrayDriver can be initialized with data', function () {
    expect(file_exists(__DIR__.'/../Data/Json/Dataset.json'))->toBeTrue();

    $rbac = RBAC::initialize(new ArrayDriver());
    $rbac->loadJsonFile(__DIR__.'/../Data/Json/Dataset.json');
    $driver = $rbac->database()->driver();
    expect($driver)->toBeInstanceOf(ArrayDriver::class);

    $permissions = Permission::all();
    expect($permissions)->toBeArray();
    expect(count($permissions))
        ->toBe(3);

    $roles = Role::all();
    expect($roles)->toBeArray();
    expect(count($roles))
        ->toBe(4);

    $subjects = Subject::all();
    expect($subjects)->toBeArray();
    expect(count($subjects))
        ->toBe(4);
});

test('RBAC can return all permissions', function () {
    $rbac = RBAC::initialize(new ArrayDriver());
    $rbac->loadJsonFile(__DIR__.'/../Data/Json/Dataset.json');

    $permissions = Permission::all();
    expect($permissions)->toBeArray();
    expect(count($permissions))
        ->toBe(3);
    foreach ($permissions as $permission) {
        expect($permission)->toBeInstanceOf(Permission::class);
    }
});

test('RBAC can create a new permission', function () {
    $rbac = RBAC::initialize(new ArrayDriver());
    $permission = new Permission();
    $permission->setName('Test permission')
        ->save();

    $permissions = Permission::all();
    expect(count($permissions))->toBe(1);
    expect($permissions[0]->id())->toBe(1);
    expect($permissions[0]->name())->toBe('Test permission');
});

test('RBAC can find a permission with specific id', function () {
    $rbac = RBAC::initialize(new ArrayDriver());
    $rbac->loadJsonFile(__DIR__.'/../Data/Json/Dataset.json');

    /** @var Permission $permission */
    $permission = Permission::find(2);
    expect($permission)->toBeInstanceOf(Permission::class);
    expect($permission->id())->toBe(2);
});

test('RBAC can edit a permission with specific id', function () {
    $rbac = RBAC::initialize(new ArrayDriver());
    $rbac->loadJsonFile(__DIR__.'/../Data/Json/Dataset.json');

    /** @var Permission $permission */
    $permission = Permission::find(2);
    expect($permission)->toBeInstanceOf(Permission::class);
    expect($permission->id())->toBe(2);
    expect($permission->name())->toBe('Change Password');
    $permission->setName('Remove password');
    expect($permission->name())->toBe('Remove password');

    // Make sure it stays the same even after recalling from the "database"
    $permission->save();
    /** @var Permission $permission */
    $permission = Permission::find(2);
    expect($permission->name())->toBe('Remove password');
});

test('RBAC can delete a permission with specific id', function () {
    $rbac = RBAC::initialize(new ArrayDriver());
    $permission = new Permission();
    $permission->setName('Test permission')
        ->save();

    /** @var Permission $permission */
    $permission = Permission::find(1);
    expect($permission)->toBeInstanceOf(Permission::class);
    expect($permission->id())->toBe(1);
    expect($permission->name())->toBe('Test permission');

    $permission->delete();

    /** @var Permission $permission */
    $permission = Permission::find(1);
    expect($permission)->toBeNull();
});

test('RBAC can return all roles', function () {
    $rbac = RBAC::initialize(new ArrayDriver());
    $rbac->loadJsonFile(__DIR__.'/../Data/Json/Dataset.json');

    $roles = Role::all();
    expect($roles)->toBeArray();
    expect(count($roles))
        ->toBe(4);
    foreach ($roles as $role) {
        expect($role)->toBeInstanceOf(Role::class);
    }
});

test('RBAC can create a new role', function () {
    $rbac = RBAC::initialize(new ArrayDriver());
    $role = new Role();
    $role->setName('root')
        ->setDescription('This is the root of the hierarchy tree')
        ->save();

    $roles = Role::all();
    expect(count($roles))->toBe(1);
    expect($roles[0]->id())->toBe(1);
    expect($roles[0]->name())->toBe('root');
    expect($roles[0]->description())->toBe('This is the root of the hierarchy tree');
});

test('RBAC can find a role with specific id', function () {
    $rbac = RBAC::initialize(new ArrayDriver());
    $rbac->loadJsonFile(__DIR__.'/../Data/Json/Dataset.json');

    /** @var Role $role */
    $role = Role::find(2);
    expect($role)->toBeInstanceOf(Role::class);
    expect($role->id())->toBe(2);
    expect($role->name())->toBe('Administrator');
    expect($role->description())->toBe('This is an administrator role');
    $permissions = $role->permissions();
    expect($permissions)
        ->toBeArray()
        ->toHaveCount(3);
    foreach ($permissions as $permission) {
        expect($permission)->toBeInstanceOf(Permission::class);
    }
});

test('RBAC returns all subjects', function () {
    $rbac = RBAC::initialize(new ArrayDriver());
    $rbac->loadJsonFile(__DIR__.'/../Data/Json/Dataset.json');

    $subjects = Subject::all();
    expect($subjects)->toBeArray();
    expect(count($subjects))
        ->toBe(4);
    foreach ($subjects as $subject) {
        expect($subject)->toBeInstanceOf(Subject::class);
    }
});

test('RBAC can create a new subject', function () {
    $rbac = RBAC::initialize(new ArrayDriver());
    $subject = new Subject();
    $subject->setName('root')
        ->save();

    $subjects = Subject::all();
    expect(count($subjects))->toBe(1);
    expect($subjects[0]->id())->toBe(1);
    expect($subjects[0]->name())->toBe('root');
});

test('RBAC can find a subject with a given id', function () {
    $rbac = RBAC::initialize(new ArrayDriver());
    $rbac->loadJsonFile(__DIR__.'/../Data/Json/Dataset.json');

    /** @var Subject $subject */
    $subject = Subject::find(2);
    expect($subject)->toBeInstanceOf(Subject::class);
    expect($subject->id())->toBe(2);
    expect($subject->name())->toBe('Alice');
});

test('RBAC can find subjects matching a given name', function () {
    $rbac = RBAC::initialize(new ArrayDriver());
    $rbac->loadJsonFile(__DIR__.'/../Data/Json/Dataset.json');

    /** @var array<Subject> $subject */
    $subject = Subject::select(['name' => 'Alice']);
    expect($subject)->toBeArray();
    expect(count($subject))->toBe(1);
    expect($subject[0])->toBeInstanceOf(Subject::class);
    expect($subject[0]->id())->toBe(2);
    expect($subject[0]->name())->toBe('Alice');

    // B_o_b, Car_o_l, r_o_ot
    /** @var array<Subject> $subject */
    $subject = Subject::select(['name' => ['like', 'o']]);
    expect($subject)->toBeArray();
    expect(count($subject))->toBe(3);
});
