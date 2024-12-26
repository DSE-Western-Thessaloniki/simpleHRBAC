<?php

use Dsewth\SimpleHRBAC\Exceptions\RBACException;
use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Workbench\App\Models\User;

require_once 'Constants.php';

test('DataHelper should throw an exception if the provided user model is not a valid Eloquent model', function () {
    expect(fn () => DataHelper::useUserModel('InvalidClass'))
        ->toThrow(\InvalidArgumentException::class);
});

test('DataHelper should import RBAC from a JSON file', function () {
    DataHelper::useUserModel(User::class);
    $data = DataHelper::importJsonFile(DATASET);
    expect($data)->toBeTrue();
});

test('DataHelper importJsonFile should return false when file cannot be read', function () {
    DataHelper::useUserModel(User::class);
    $data = DataHelper::importJsonFile('Non-existent-file.json');
    expect($data)->toBeFalse();

    // Not readable
    $data = DataHelper::importJsonFile('/etc/shadow');
    expect($data)->toBeFalse();

    // Not a JSON file
    $data = DataHelper::importJsonFile('/etc/passwd');
    expect($data)->toBeFalse();

    // Empty JSON file
    $tmpfile = tempnam('/tmp', 'tst');
    $data = DataHelper::importJsonFile($tmpfile);
    expect($data)->toBeFalse();
});

test('DataHelper should import roles', function () {
    DataHelper::useUserModel(User::class);
    DataHelper::importData([
        'Roles' => [
            [
                'id' => 1,
                'name' => 'Admin',
            ],
            [
                'id' => 2,
                'name' => 'Manager',
                'parent_id' => 1,
            ],
        ],
    ]);
    expect(Role::count())->toBeGreaterThan(0);
});

test('DataHelper should throw an exception if roles is not an array', function () {
    DataHelper::useUserModel(User::class);
    expect(fn () => DataHelper::importData([
        'Roles' => false,
    ]))->toThrow(RBACException::class);
    expect(Role::count())->toBe(0);
});

test('DataHelper should import permissions', function () {
    DataHelper::useUserModel(User::class);
    DataHelper::importData([
        'Permissions' => [
            [
                'id' => 1,
                'name' => 'Create Posts',
            ],
            [
                'id' => 2,
                'name' => 'Edit Posts',
            ],
        ],
    ]);
    expect(Permission::count())->toBeGreaterThan(0);
});

test('DataHelper should throw an exception if permissions is not an array', function () {
    DataHelper::useUserModel(User::class);
    expect(fn () => DataHelper::importData([
        'Permissions' => false,
    ]))->toThrow(RBACException::class);
    expect(Permission::count())->toBe(0);
});

test('DataHelper should import users', function () {
    DataHelper::useUserModel(User::class);
    DataHelper::importData([
        'Users' => [
            [
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'johndoe@example.com',
                'password' => 'password',
            ],
            [
                'id' => 2,
                'name' => 'Jane Doe',
                'email' => 'janedoe@example.com',
                'password' => 'password',
            ],
        ],
    ]);
    expect(User::count())->toBeGreaterThan(0);
});

test('DataHelper should throw an exception if users is not an array', function () {
    DataHelper::useUserModel(User::class);
    expect(fn () => DataHelper::importData([
        'Users' => false,
    ]))->toThrow(RBACException::class);
    expect(User::count())->toBe(0);
});
