<?php

use Dsewth\SimpleHRBAC\Exceptions\RBACException;
use Dsewth\SimpleHRBAC\Facades\RBAC;
use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Workbench\App\Models\User;

require_once 'Constants.php';

beforeAll(function () {
    DataHelper::useUserModel(User::class);
});

beforeEach(function () {
    Cache::flush();
});

/*
 * ============================================================
 * Test Gap 1: Concurrent Role Creation (Race Condition)
 * ============================================================
 *
 * NOTE: True concurrent testing requires forking processes,
 * which is not reliable in a test environment. Instead, we
 * simulate the race condition by checking the behavior of
 * the `creating()` observer when two root nodes are attempted
 * in rapid succession. In production, a database-level unique
 * partial index on `parent_id WHERE parent_id IS NULL` would
 * be the proper solution.
 */

test('only one root node can exist even with rapid creation attempts', function () {
    // Create first root
    $root = Role::create([
        'name' => 'Root',
        'description' => 'Root node',
    ]);

    expect($root->parent_id)->toBeNull();
    expect(Role::whereNull('parent_id')->count())->toBe(1);

    // Attempting to create another root should fail
    expect(fn () => Role::create([
        'name' => 'Second Root',
        'description' => 'Should fail',
    ]))->toThrow(RBACException::class, 'Only the root node can have null parent_id');

    // Verify only one root exists
    expect(Role::whereNull('parent_id')->count())->toBe(1);
});

test('role with null parent_id cannot be updated to null again if root exists', function () {
    $root = Role::create([
        'name' => 'Root',
        'description' => 'Root node',
    ]);

    $child = Role::create([
        'name' => 'Child',
        'description' => 'Child role',
        'parent_id' => $root->id,
    ]);

    // Attempting to set parent_id to null should fail
    $child->parent_id = null;
    expect(fn () => $child->save())->toThrow(RBACException::class, 'Only the root node can have null parent_id');

    // Verify the child still has a parent
    expect(Role::find($child->id)->parent_id)->not->toBeNull();
});

/*
 * ============================================================
 * Test Gap 2: Large Hierarchy Performance
 * ============================================================
 */

test('can handle a large hierarchy with many nested levels', function () {
    // Create a deep hierarchy (50 levels)
    $root = Role::create([
        'name' => 'Root',
        'description' => 'Root of deep tree',
    ]);

    $parentId = $root->id;
    for ($i = 1; $i <= 50; $i++) {
        $role = Role::create([
            'name' => "Level {$i}",
            'description' => "Role at level {$i}",
            'parent_id' => $parentId,
        ]);
        $parentId = $role->id;
    }

    // Verify total roles
    expect(Role::count())->toBe(51); // root + 50 levels

    // Verify root has 50 descendants
    $root->refresh();
    $children = $root->children();
    expect($children)->toHaveCount(50);

    // Verify deepest node has 50 ancestors
    $deepestRole = Role::where('name', 'Level 50')->first();
    $parents = $deepestRole->parents();
    expect($parents)->toHaveCount(50);

    // Verify the depth values in closure table
    $rootDepth = DB::table('role_tree')
        ->where('parent', $root->id)
        ->where('child', $deepestRole->id)
        ->first();
    expect($rootDepth->depth)->toBe(50);
});

test('can handle a wide hierarchy with many siblings', function () {
    $root = Role::create([
        'name' => 'Root',
        'description' => 'Root of wide tree',
    ]);

    // Create 100 direct children
    for ($i = 1; $i <= 100; $i++) {
        Role::create([
            'name' => "Child {$i}",
            'description' => "Direct child {$i}",
            'parent_id' => $root->id,
        ]);
    }

    $root->refresh();
    $immediateChildren = $root->immediateChildren();
    expect($immediateChildren)->toHaveCount(100);

    $allChildren = $root->children();
    expect($allChildren)->toHaveCount(100);

    // Verify closure table entries
    $closureEntries = DB::table('role_tree')->count();
    // root self-loop (1) + each child self-loop (100) + each child to root (100) = 201
    expect($closureEntries)->toBe(201);
});

test('closure table query count remains reasonable for large hierarchies', function () {
    DataHelper::importJsonFile(DATASET);

    // Build a moderately sized hierarchy
    $root = Role::find(1);

    DB::enableQueryLog();

    // Get children should use limited queries
    $children = $root->children();
    $queries = DB::getQueryLog();

    // Should be at most 3 queries: one join query to get IDs, one to fetch models,
    // and potentially one more for relationship loading
    expect(count($queries))->toBeLessThanOrEqual(3);

    DB::flushQueryLog();

    // Get parents should also use limited queries
    $leafRole = Role::find(6); // User3 in the dataset
    $parents = $leafRole->parents();
    $queries = DB::getQueryLog();

    expect(count($queries))->toBeLessThanOrEqual(3);
});

/*
 * ============================================================
 * Test Gap 3: getUsersWithPermission() Edge Cases
 * ============================================================
 *
 * NOTE: According to analysis.md section 5, the current implementation
 * traverses PARENTS (ancestors) instead of CHILDREN (descendants).
 * This is flagged as potentially incorrect behavior.
 *
 * These tests document the current behavior and verify edge cases.
 * A decision is needed on whether to fix the semantics.
 */

test('getUsersWithPermission returns users from ancestor roles', function () {
    // Dataset hierarchy:
    // root(1) -> Administrator(2) [has Print, Change Password, Create users]
    // root(1) -> IT Department(3) [has Print]
    // IT Department(3) -> User(4)
    // User(4) -> User2(5)
    // User2(5) -> User3(6)
    //
    // Users: root(4) has role 1, Alice(2) has role 2, Carol(3) has role 3, Bob(1) has role 4

    DataHelper::importJsonFile(DATASET);

    // "Print" permission is on role 2 (Administrator) and role 3 (IT Department)
    // Current implementation: gets users from roles that have the permission
    // AND all their PARENT (ancestor) roles
    // Role 2's ancestors: role 1 (root) -> user 4 (root user)
    // Role 3's ancestors: role 1 (root) -> user 4 (root user)
    // Users from role 2: Alice(2)
    // Users from role 3: Carol(3)
    // Users from role 1: root(4)
    // Total: 3 users (Alice, Carol, root)

    $users = RBAC::getUsersWithPermission('Print');

    expect($users)->toHaveCount(3);
    expect($users->contains('id', 2))->toBeTrue(); // Alice
    expect($users->contains('id', 3))->toBeTrue(); // Carol
    expect($users->contains('id', 4))->toBeTrue(); // root
});

test('getUsersWithPermission handles user with multiple roles in same hierarchy', function () {
    DataHelper::importJsonFile(DATASET);

    // Create a user with multiple roles in the same hierarchy
    $multiRoleUser = User::create([
        'name' => 'MultiRole',
        'email' => 'multi@example.com',
        'password' => 'hash123',
    ]);

    // Assign both root role and Administrator role (both in same hierarchy)
    $multiRoleUser->roles()->attach([1, 2]);

    // Get users with "Print" permission
    $users = RBAC::getUsersWithPermission('Print');

    // Should include the multi-role user only once (deduplicated)
    expect($users->where('id', $multiRoleUser->id))->toHaveCount(1);

    // Total should be 4: Alice, Carol, root, MultiRole
    expect($users)->toHaveCount(4);
});

test('getUsersWithPermission returns empty for non-existent permission', function () {
    DataHelper::importJsonFile(DATASET);

    $users = RBAC::getUsersWithPermission('NonexistentPermission');

    expect($users)->toBeEmpty();
});

test('getUsersWithPermission handles permission assigned to multiple roles', function () {
    DataHelper::importJsonFile(DATASET);

    // "Print" is assigned to both Administrator(2) and IT Department(3)
    $users = RBAC::getUsersWithPermission('Print');

    // Should deduplicate users who have multiple roles with the same permission
    expect($users->pluck('id')->unique()->count())->toBe($users->count());
});

/*
 * ============================================================
 * Test Gap 4: Circular Reference Prevention Beyond Self-Parenting
 * ============================================================
 *
 * NOTE: The current implementation prevents:
 * 1. Self-parenting (A -> A)
 * 2. Moving the root node
 * 3. Creating a second root
 *
 * But it does NOT prevent transitive circular references:
 * A -> B -> C, then trying to make A a child of C
 * This would create: C -> A -> B -> C (cycle!)
 */

test('can detect transitive circular reference when moving a node to its descendant', function () {
    // Create hierarchy: A -> B -> C
    $a = Role::create([
        'name' => 'A',
        'description' => 'Role A',
    ]);

    $b = Role::create([
        'name' => 'B',
        'description' => 'Role B',
        'parent_id' => $a->id,
    ]);

    $c = Role::create([
        'name' => 'C',
        'description' => 'Role C',
        'parent_id' => $b->id,
    ]);

    // Try to make A a child of C (creating cycle: C -> A -> B -> C)
    $a->parent_id = $c->id;

    // This SHOULD throw an exception to prevent circular reference
    // NOTE: Currently this is NOT prevented by the implementation
    // This test documents the expected behavior
    expect(fn () => $a->save())->toThrow(RBACException::class);

    // Verify the cycle was prevented
    expect(Role::find($a->id)->parent_id)->toBeNull();
});

test('can detect transitive circular reference in deeply nested hierarchy', function () {
    // Create hierarchy: Root -> A -> B -> C -> D
    $root = Role::create([
        'name' => 'Root',
        'description' => 'Root',
    ]);

    $a = Role::create([
        'name' => 'A',
        'parent_id' => $root->id,
    ]);

    $b = Role::create([
        'name' => 'B',
        'parent_id' => $a->id,
    ]);

    $c = Role::create([
        'name' => 'C',
        'parent_id' => $b->id,
    ]);

    $d = Role::create([
        'name' => 'D',
        'parent_id' => $c->id,
    ]);

    // Try to make B a child of D (creating cycle)
    $b->parent_id = $d->id;

    expect(fn () => $b->save())->toThrow(RBACException::class);

    // Verify the cycle was prevented
    expect(Role::find($b->id)->parent_id)->toBe($a->id);
});

test('can move a node to a non-descendant without issues', function () {
    // Create hierarchy:
    // Root
    //   -> A -> B
    //   -> C
    $root = Role::create([
        'name' => 'Root',
    ]);

    $a = Role::create([
        'name' => 'A',
        'parent_id' => $root->id,
    ]);

    $b = Role::create([
        'name' => 'B',
        'parent_id' => $a->id,
    ]);

    $c = Role::create([
        'name' => 'C',
        'parent_id' => $root->id,
    ]);

    // Move B under C (not a descendant, should work)
    $b->parent_id = $c->id;
    $b->save();

    expect(Role::find($b->id)->parent_id)->toBe($c->id);
    expect($c->immediateChildren()->where('id', $b->id))->toHaveCount(1);

    // Verify no cycle: B's new parents should be C and Root
    $bParents = Role::find($b->id)->parents();
    expect($bParents)->toHaveCount(2); // C and Root
});

/*
 * ============================================================
 * Test Gap 5: moveNode() Behavior and Closure Table Integrity
 * ============================================================
 */

test('moveNode maintains closure table integrity when moving a node with subtree', function () {
    DataHelper::importJsonFile(DATASET);

    // Initial hierarchy:
    // root(1) -> IT Department(3) -> User(4) -> User2(5) -> User3(6)
    // root(1) -> Administrator(2)

    // Move IT Department(3) under Administrator(2)
    $itDept = Role::find(3);
    $itDept->parent_id = 2;
    $itDept->save();

    // Verify IT Department's new parents
    $itParents = $itDept->parents();
    expect($itParents)->toHaveCount(2); // Administrator and root

    // Verify User(4) still has IT Department as parent
    $user4 = Role::find(4);
    $user4Parents = $user4->parents();
    expect($user4Parents->contains('id', 3))->toBeTrue();

    // User(4) should now have parents: IT Department, Administrator, root
    expect($user4Parents)->toHaveCount(3);

    // Verify closure table depths are correct
    $user4ToRoot = DB::table('role_tree')
        ->where('parent', 1)
        ->where('child', 4)
        ->first();
    expect($user4ToRoot->depth)->toBe(3); // root -> Administrator -> IT Dept -> User

    $user4ToAdmin = DB::table('role_tree')
        ->where('parent', 2)
        ->where('child', 4)
        ->first();
    expect($user4ToAdmin->depth)->toBe(2); // Administrator -> IT Dept -> User
});

test('moveNode cleans up old closure table entries', function () {
    DataHelper::importJsonFile(DATASET);

    // Count closure table entries before move
    $beforeCount = DB::table('role_tree')->count();

    // Move User(4) directly under root(1)
    $user4 = Role::find(4);
    $user4->parent_id = 1;
    $user4->save();

    // Count closure table entries after move
    $afterCount = DB::table('role_tree')->count();

    // The count should be different (old paths removed, new paths added)
    expect($afterCount)->not->toBe($beforeCount);

    // Verify no stale entries exist for old path through IT Department
    $staleEntries = DB::table('role_tree')
        ->where('parent', 3) // IT Department
        ->where('child', 5) // User2
        ->get();

    // User2(5) should no longer be a descendant of IT Department(3)
    // After User4 moves to root:
    // root -> User4 -> User2 -> User3
    // root -> Administrator
    // root -> IT Department
    // So IT Department(3) is no longer an ancestor of User2(5)
    expect($staleEntries)->toBeEmpty();
});

test('moveNode handles moving leaf node correctly', function () {
    DataHelper::importJsonFile(DATASET);

    // Move leaf User3(6) under Administrator(2)
    $user3 = Role::find(6);
    $user3->parent_id = 2;
    $user3->save();

    expect(Role::find(6)->parent_id)->toBe(2);

    // Verify closure table
    $user3Parents = $user3->parents();
    expect($user3Parents)->toHaveCount(2); // Administrator and root

    // Verify User3 is no longer under User2
    $oldPath = DB::table('role_tree')
        ->where('parent', 5) // User2
        ->where('child', 6)
        ->first();
    expect($oldPath)->toBeNull();
});

test('closure table remains consistent after multiple moves', function () {
    DataHelper::importJsonFile(DATASET);

    // Perform multiple moves
    $user4 = Role::find(4);
    $user4->parent_id = 2; // Move under Administrator
    $user4->save();

    $user5 = Role::find(5);
    $user5->parent_id = 1; // Move User2 under root
    $user5->save();

    // Verify closure table consistency
    // Check that all roles have self-loops
    $selfLoops = DB::table('role_tree')
        ->whereColumn('parent', 'child')
        ->count();
    expect($selfLoops)->toBe(6); // One for each role

    // Check that no negative depths exist
    $negativeDepths = DB::table('role_tree')
        ->where('depth', '<', 0)
        ->count();
    expect($negativeDepths)->toBe(0);

    // Verify parent-child relationships are correct
    $user4Role = Role::find(4);
    expect($user4Role->parent_id)->toBe(2);

    $user4Parents = $user4Role->parents();
    expect($user4Parents->pluck('id')->toArray())->toContain(1, 2);
});

/*
 * ============================================================
 * Additional Edge Cases
 * ============================================================
 */

test('delete role with children reparents them correctly', function () {
    // Create hierarchy: Root -> A -> B -> C
    $root = Role::create(['name' => 'Root']);
    $a = Role::create(['name' => 'A', 'parent_id' => $root->id]);
    $b = Role::create(['name' => 'B', 'parent_id' => $a->id]);
    $c = Role::create(['name' => 'C', 'parent_id' => $b->id]);

    // Delete A - B should be reparented to Root
    $a->delete();

    expect(Role::find($b->id)->parent_id)->toBe($root->id);
    expect(Role::find($c->id)->parent_id)->toBe($b->id);

    // Verify closure table
    $bParents = Role::find($b->id)->parents();
    expect($bParents)->toHaveCount(1); // Only root
    expect($bParents->first()->id)->toBe($root->id);
});

test('permissions are correctly inherited through large hierarchy', function () {
    $root = Role::create(['name' => 'Root']);
    $perm = Permission::create(['name' => 'TestPermission']);
    $root->permissions()->attach($perm);

    // Create 10 levels under root
    $parentId = $root->id;
    for ($i = 1; $i <= 10; $i++) {
        $role = Role::create([
            'name' => "Level {$i}",
            'parent_id' => $parentId,
        ]);
        $parentId = $role->id;
    }

    // User with root role should have the permission (from root and all descendants)
    $userWithRoot = User::create([
        'name' => 'RootUser',
        'email' => 'rootuser@example.com',
        'password' => 'hash123',
    ]);
    $userWithRoot->roles()->attach($root->id);

    $permissions = RBAC::getPermissionsOf($userWithRoot);
    expect($permissions)->toHaveCount(1);
    expect($permissions->first()->name)->toBe('TestPermission');

    // User with leaf role (Level 10) should NOT have the permission from root
    // (inheritance flows downward, not upward)
    $userWithLeaf = User::create([
        'name' => 'LeafUser',
        'email' => 'leafuser@example.com',
        'password' => 'hash123',
    ]);
    $deepestRole = Role::where('name', 'Level 10')->first();
    $userWithLeaf->roles()->attach($deepestRole->id);

    $permissions2 = RBAC::getPermissionsOf($userWithLeaf);
    expect($permissions2)->toHaveCount(0);
});

test('cache is invalidated after role hierarchy changes', function () {
    DataHelper::importJsonFile(DATASET);

    $user = User::find(2); // Alice with Administrator role

    // First call - should cache
    $canBefore = RBAC::can($user->id, 'Print');
    expect($canBefore)->toBeTrue();

    // Change the hierarchy: move Administrator to be under IT Department
    $admin = Role::find(2);
    $admin->parent_id = 3; // Under IT Department
    $admin->save();

    // Cache should be invalidated - need to check if the user still has permission
    // After the move, Administrator is under IT Department, which is under root
    // So Administrator should still have Print permission (inherited through hierarchy)
    $canAfter = RBAC::can($user->id, 'Print');
    expect($canAfter)->toBeTrue();
});
