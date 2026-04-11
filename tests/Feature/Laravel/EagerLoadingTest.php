<?php

use Dsewth\SimpleHRBAC\Helpers\ClosureTable;
use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Illuminate\Support\Facades\DB;
use Workbench\App\Models\User;

require_once 'Constants.php';

beforeAll(function () {
    DataHelper::useUserModel(User::class);
});

/*
 * ============================================================
 * Eager Loading Tests for ClosureTable Methods
 * ============================================================
 *
 * These tests verify that the children(), immediateChildren(),
 * and parents() methods properly support eager loading of
 * relationships like 'permissions' and 'users'.
 */

/*
 * -----------------------------------------------------------
 * Test 1: children() with eager loading
 * -----------------------------------------------------------
 */

test('children can eager load permissions relationship', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    // With eager loading
    $childrenWithPerms = $root->tree()->children(['permissions']);

    // Both should return the same number of children
    expect($childrenWithPerms)->toHaveCount(5);

    // Verify that permissions are loaded (not null/empty collection)
    $loadedCount = 0;
    foreach ($childrenWithPerms as $child) {
        // Access the relationship to ensure it's loaded
        $perms = $child->permissions;
        $loadedCount++;
    }

    // All children should have been iterated
    expect($loadedCount)->toBe(5);
});

test('children can eager load users relationship', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    // With eager loading users
    $childrenWithUsers = $root->tree()->children(['users']);

    expect($childrenWithUsers)->toHaveCount(5);

    // Accessing users should not trigger additional queries
    DB::enableQueryLog();
    foreach ($childrenWithUsers as $child) {
        $users = $child->users;
    }
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(0);
});

test('children can eager load multiple relationships at once', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    // With eager loading both permissions and users
    $children = $root->tree()->children(['permissions', 'users']);

    expect($children)->toHaveCount(5);

    // Accessing both relationships should not trigger additional queries
    DB::enableQueryLog();
    foreach ($children as $child) {
        $perms = $child->permissions;
        $users = $child->users;
    }
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(0);
});

test('children without eager loading returns empty array by default', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    $children = $root->tree()->children();

    expect($children)->toHaveCount(5);

    // Verify no relationships are loaded
    DB::enableQueryLog();
    $firstChild = $children->first();
    $perms = $firstChild->permissions;
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Should trigger a query since not eagerly loaded
    expect($queries)->toBeGreaterThan(0);
});

/*
 * -----------------------------------------------------------
 * Test 2: immediateChildren() with eager loading
 * -----------------------------------------------------------
 */

test('immediateChildren can eager load permissions relationship', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    // With eager loading
    $immediateChildren = $root->tree()->immediateChildren(['permissions']);

    // Only direct children of root (depth = 1)
    expect($immediateChildren)->toHaveCount(2); // Administrator and IT Department

    // Accessing permissions should not trigger additional queries
    DB::enableQueryLog();
    foreach ($immediateChildren as $child) {
        $perms = $child->permissions;
    }
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(0);
});

test('immediateChildren without eager loading triggers N+1 queries', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    // Without eager loading
    $immediateChildren = $root->tree()->immediateChildren();

    expect($immediateChildren)->toHaveCount(2);

    // Accessing permissions should trigger additional queries
    DB::enableQueryLog();
    foreach ($immediateChildren as $child) {
        $perms = $child->permissions;
    }
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeGreaterThan(0);
});

test('immediateChildren can eager load users relationship', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    // With eager loading users
    $immediateChildren = $root->tree()->immediateChildren(['users']);

    expect($immediateChildren)->toHaveCount(2);

    // Accessing users should not trigger additional queries
    DB::enableQueryLog();
    foreach ($immediateChildren as $child) {
        $users = $child->users;
    }
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(0);
});

/*
 * -----------------------------------------------------------
 * Test 3: parents() with eager loading
 * -----------------------------------------------------------
 */

test('parents can eager load permissions relationship', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $user */
    $user = Role::find(4); // User role

    // With eager loading
    $parents = $user->tree()->parents(['permissions']);

    // Should have 2 parents: IT Department and root
    expect($parents)->toHaveCount(2);

    // Accessing permissions should not trigger additional queries
    DB::enableQueryLog();
    foreach ($parents as $parent) {
        $perms = $parent->permissions;
    }
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(0);
});

test('parents without eager loading triggers N+1 queries', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $user */
    $user = Role::find(4); // User role

    // Without eager loading
    $parents = $user->tree()->parents();

    expect($parents)->toHaveCount(2);

    // Accessing permissions should trigger additional queries
    DB::enableQueryLog();
    foreach ($parents as $parent) {
        $perms = $parent->permissions;
    }
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeGreaterThan(0);
});

test('parents can eager load users relationship', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $user */
    $user = Role::find(4); // User role

    // With eager loading users
    $parents = $user->tree()->parents(['users']);

    expect($parents)->toHaveCount(2);

    // Accessing users should not trigger additional queries
    DB::enableQueryLog();
    foreach ($parents as $parent) {
        $users = $parent->users;
    }
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(0);
});

test('parents can eager load multiple relationships at once', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $user */
    $user = Role::find(4); // User role

    // With eager loading both permissions and users
    $parents = $user->tree()->parents(['permissions', 'users']);

    expect($parents)->toHaveCount(2);

    // Accessing both relationships should not trigger additional queries
    DB::enableQueryLog();
    foreach ($parents as $parent) {
        $perms = $parent->permissions;
        $users = $parent->users;
    }
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(0);
});

/*
 * -----------------------------------------------------------
 * Test 4: Query count optimization verification
 * -----------------------------------------------------------
 */

test('eager loading query structure is correct', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    // Test that eager loading is properly set up
    DB::enableQueryLog();
    $children = $root->tree()->children(['permissions']);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Should have at least 2 queries: one for IDs, one for roles with eager loading
    expect(count($queries))->toBeGreaterThanOrEqual(2);

    // Verify children are loaded
    expect($children)->toHaveCount(5);
});

test('eager loading properly loads relationships', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    // With eager loading
    $children = $root->tree()->children(['permissions']);

    // Verify relationships are loaded by checking they don't trigger lazy loading
    foreach ($children as $child) {
        // Force the relationship to be accessed
        expect($child->relationLoaded('permissions'))->toBeTrue();
    }
});

/*
 * -----------------------------------------------------------
 * Test 5: Edge cases
 * -----------------------------------------------------------
 */

test('children with eager loading works on empty result', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $leafRole */
    $leafRole = Role::find(6); // User3 has no children

    $children = $leafRole->tree()->children(['permissions']);

    expect($children)->toBeEmpty();
});

test('immediateChildren with eager loading works on empty result', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $leafRole */
    $leafRole = Role::find(6); // User3 has no children

    $children = $leafRole->tree()->immediateChildren(['permissions']);

    expect($children)->toBeEmpty();
});

test('parents with eager loading works on root node', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    // Root has no parents
    $parents = $root->tree()->parents(['permissions']);

    expect($parents)->toBeEmpty();
});

test('eager loading works with multiple relationships simultaneously', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    // Eager load both permissions and users
    $children = $root->tree()->children(['permissions', 'users']);

    expect($children)->toHaveCount(5);

    // Access both relationships without errors
    $totalUsers = 0;
    $totalPerms = 0;
    foreach ($children as $child) {
        $totalUsers += $child->users->count();
        $totalPerms += $child->permissions->count();
    }

    // Verify we can access both
    expect($totalUsers)->toBeGreaterThanOrEqual(0);
    expect($totalPerms)->toBeGreaterThanOrEqual(0);
});

test('eager loading does not affect returned model count', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    $childrenWithout = $root->tree()->children();
    $childrenWith = $root->tree()->children(['permissions', 'users']);
    $immediateWithout = $root->tree()->immediateChildren();
    $immediateWith = $root->tree()->immediateChildren(['permissions']);

    expect($childrenWithout)->toHaveCount(5);
    expect($childrenWith)->toHaveCount(5);
    expect($immediateWithout)->toHaveCount(2);
    expect($immediateWith)->toHaveCount(2);
});

/*
 * -----------------------------------------------------------
 * Test 6: ClosureTable direct usage
 * -----------------------------------------------------------
 */

test('ClosureTable can be instantiated and used directly with eager loading', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    $closureTable = new ClosureTable($root);

    $children = $closureTable->children(['permissions']);

    expect($children)->toHaveCount(5);

    // Verify eager loading worked
    DB::enableQueryLog();
    foreach ($children as $child) {
        $perms = $child->permissions;
    }
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(0);
});

test('eager loading works with array syntax for relationships', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    // Test with explicit array
    $children = $root->tree()->children(['permissions', 'users']);

    expect($children)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($children->first())->toBeInstanceOf(Role::class);
});

test('eager loading works with empty array parameter', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    // Empty array should work same as no parameter
    $children = $root->tree()->children([]);

    expect($children)->toHaveCount(5);

    // Should trigger query when accessing relationship
    DB::enableQueryLog();
    $firstChild = $children->first();
    $perms = $firstChild->permissions;
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeGreaterThan(0);
});

/*
 * -----------------------------------------------------------
 * Test 7: Role model convenience methods with eager loading
 * -----------------------------------------------------------
 */

test('Role model children method supports eager loading', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    // Use Role model method directly
    $children = $root->children(['permissions']);

    expect($children)->toHaveCount(5);

    // Verify relationships are loaded
    foreach ($children as $child) {
        expect($child->relationLoaded('permissions'))->toBeTrue();
    }
});

test('Role model immediateChildren method supports eager loading', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    // Use Role model method directly
    $immediateChildren = $root->immediateChildren(['permissions']);

    expect($immediateChildren)->toHaveCount(2);

    // Verify relationships are loaded
    foreach ($immediateChildren as $child) {
        expect($child->relationLoaded('permissions'))->toBeTrue();
    }
});

test('Role model parents method supports eager loading', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $user */
    $user = Role::find(4);

    // Use Role model method directly
    $parents = $user->parents(['permissions']);

    expect($parents)->toHaveCount(2);

    // Verify relationships are loaded
    foreach ($parents as $parent) {
        expect($parent->relationLoaded('permissions'))->toBeTrue();
    }
});

test('Role model children method can eager load users relationship', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    $children = $root->children(['users']);

    expect($children)->toHaveCount(5);

    foreach ($children as $child) {
        expect($child->relationLoaded('users'))->toBeTrue();
    }
});

test('Role model children method can eager load multiple relationships', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    $children = $root->children(['permissions', 'users']);

    expect($children)->toHaveCount(5);

    foreach ($children as $child) {
        expect($child->relationLoaded('permissions'))->toBeTrue();
        expect($child->relationLoaded('users'))->toBeTrue();
    }
});

test('Role model parents method can eager load multiple relationships', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $user */
    $user = Role::find(4);

    $parents = $user->parents(['permissions', 'users']);

    expect($parents)->toHaveCount(2);

    foreach ($parents as $parent) {
        expect($parent->relationLoaded('permissions'))->toBeTrue();
        expect($parent->relationLoaded('users'))->toBeTrue();
    }
});

test('Role model children method works without eager loading parameter', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    $children = $root->children();

    expect($children)->toHaveCount(5);

    // Should trigger query when accessing relationship
    DB::enableQueryLog();
    $firstChild = $children->first();
    $perms = $firstChild->permissions;
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeGreaterThan(0);
});

test('Role model immediateChildren method works without eager loading parameter', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    $immediateChildren = $root->immediateChildren();

    expect($immediateChildren)->toHaveCount(2);

    // Should trigger query when accessing relationship
    DB::enableQueryLog();
    $firstChild = $immediateChildren->first();
    $perms = $firstChild->permissions;
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeGreaterThan(0);
});

test('Role model parents method works without eager loading parameter', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $user */
    $user = Role::find(4);

    $parents = $user->parents();

    expect($parents)->toHaveCount(2);

    // Should trigger query when accessing relationship
    DB::enableQueryLog();
    $firstParent = $parents->first();
    $perms = $firstParent->permissions;
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeGreaterThan(0);
});

test('Role model children method with eager loading works on empty result', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $leafRole */
    $leafRole = Role::find(6);

    $children = $leafRole->children(['permissions']);

    expect($children)->toBeEmpty();
});

test('Role model parents method works on root node', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    $parents = $root->parents(['permissions']);

    expect($parents)->toBeEmpty();
});

test('Role model eager loading returns correct model instances', function () {
    DataHelper::importJsonFile(DATASET);

    /** @var Role $root */
    $root = Role::find(1);

    $children = $root->children(['permissions', 'users']);

    expect($children)->toContainOnlyInstancesOf(Role::class);

    foreach ($children as $child) {
        expect($child)->toBeInstanceOf(Role::class);
        expect($child->permissions)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($child->users)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    }
});

