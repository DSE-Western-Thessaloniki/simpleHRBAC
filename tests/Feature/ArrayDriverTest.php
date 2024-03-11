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

test('HRBAC can be initialized with ArrayDriver', function () {
    $rbac = RBAC::initialize(new ArrayDriver());
    expect($rbac)->toBeInstanceOf(RBAC::class);
    unset($rbac);
});

test('ArrayDriver can be initialized with data', function () {
    expect(file_exists(__DIR__.'/../Data/Json/Dataset.json'))->toBeTrue();

    $data = file_get_contents(__DIR__.'/../Data/Json/Dataset.json');
    $driver = ArrayDriver::withData(json_decode($data, true));
    expect($driver)->toBeInstanceOf(ArrayDriver::class);

    $permissions = $driver->permissions();
    expect($permissions)->toBeArray();
    expect(count($permissions))
        ->toBe(3);

    $roles = $driver->roles();
    expect($roles)->toBeArray();
    expect(count($roles))
        ->toBe(4);

    $subjects = $driver->subjects();
    expect($subjects)->toBeArray();
    expect(count($subjects))
        ->toBe(4);
});

test('RBAC returns all permissions', function () {
    $data = file_get_contents(__DIR__.'/../Data/Json/Dataset.json');
    $rbac = RBAC::initialize(ArrayDriver::withData(json_decode($data, true)));

    $permissions = $rbac->getAllPermissions();
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
    var_dump($permission);

    $permissions = $rbac->getAllPermissions();
    expect(count($permissions))->toBe(1);
    expect($permissions[0]->id())->toBe(1);
    expect($permissions[0]->name())->toBe('Test permission');
});

test('RBAC returns all roles', function () {
    $data = file_get_contents(__DIR__.'/../Data/Json/Dataset.json');
    $rbac = RBAC::initialize(ArrayDriver::withData(json_decode($data, true)));

    $roles = $rbac->getAllRoles();
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

    $roles = $rbac->getAllRoles();
    expect(count($roles))->toBe(1);
    expect($roles[0]->id())->toBe(1);
    expect($roles[0]->name())->toBe('root');
    expect($roles[0]->description())->toBe('This is the root of the hierarchy tree');
});

test('RBAC returns all subjects', function () {
    $data = file_get_contents(__DIR__.'/../Data/Json/Dataset.json');
    $rbac = RBAC::initialize(ArrayDriver::withData(json_decode($data, true)));

    $subjects = $rbac->getAllSubjects();
    expect($subjects)->toBeArray();
    expect(count($subjects))
        ->toBe(4);
    foreach ($subjects as $subject) {
        expect($subject)->toBeInstanceOf(Subject::class);
    }
});

test('RBAC can find a subject with a given id', function () {
    $data = file_get_contents(__DIR__.'/../Data/Json/Dataset.json');
    $rbac = RBAC::initialize(ArrayDriver::withData(json_decode($data, true)));

    $subject = $rbac->getSubjectById(2);
    expect($subject)->toBeInstanceOf(Subject::class);
    expect($subject->id())->toBe(2);
    expect($subject->name())->toBe('Alice');
});

test('RBAC can find subjects matching a given name', function () {
    $data = file_get_contents(__DIR__.'/../Data/Json/Dataset.json');
    $rbac = RBAC::initialize(ArrayDriver::withData(json_decode($data, true)));

    $subject = $rbac->getSubjectsByName('Alice');
    expect($subject)->toBeArray();
    expect(count($subject))->toBe(1);
    expect($subject[0])->toBeInstanceOf(Subject::class);
    expect($subject[0]->id())->toBe(2);
    expect($subject[0]->name())->toBe('Alice');

    // B_o_b, Car_o_l, r_o_ot
    $subject = $rbac->getSubjectsByName('o');
    expect($subject)->toBeArray();
    expect(count($subject))->toBe(3);
});
