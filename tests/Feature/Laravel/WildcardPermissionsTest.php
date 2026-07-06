<?php

use Dsewth\SimpleHRBAC\Facades\RBAC;
use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Helpers\PermissionWildcard;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Illuminate\Support\Collection;
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
 * PermissionWildcard helper
 * ============================================================
 */

test('isPattern recognises wildcard names', function () {
    expect(PermissionWildcard::isPattern('view.*'))->toBeTrue();
    expect(PermissionWildcard::isPattern('*'))->toBeTrue();
    expect(PermissionWildcard::isPattern('user_*'))->toBeTrue();
    expect(PermissionWildcard::isPattern('*.read'))->toBeTrue();
    expect(PermissionWildcard::isPattern('a.*.b'))->toBeTrue();
    expect(PermissionWildcard::isPattern('view.1'))->toBeFalse();
    expect(PermissionWildcard::isPattern(''))->toBeFalse();
});

test('matches honours literal names', function () {
    expect(PermissionWildcard::matches('view.1', 'view.1'))->toBeTrue();
    expect(PermissionWildcard::matches('view.1', 'view.2'))->toBeFalse();
    expect(PermissionWildcard::matches('view.1', 'view.1.extra'))->toBeFalse();
});

test('matches treats * as greedy across dots', function () {
    expect(PermissionWildcard::matches('view.*', 'view.1'))->toBeTrue();
    expect(PermissionWildcard::matches('view.*', 'view.x'))->toBeTrue();
    expect(PermissionWildcard::matches('view.*', 'view.users.1'))->toBeTrue();
    expect(PermissionWildcard::matches('view.*', 'view.a.b.c'))->toBeTrue();
    expect(PermissionWildcard::matches('view.*', 'viewer.1'))->toBeFalse();
    expect(PermissionWildcard::matches('view.*', 'edit.1'))->toBeFalse();
    expect(PermissionWildcard::matches('view.*', 'view.'))->toBeTrue();
});

test('matches supports wildcards anywhere and multiple wildcards', function () {
    expect(PermissionWildcard::matches('*', 'anything.at.all'))->toBeTrue();
    expect(PermissionWildcard::matches('*', ''))->toBeTrue();
    expect(PermissionWildcard::matches('*.read', 'posts.read'))->toBeTrue();
    expect(PermissionWildcard::matches('*.read', 'users.admin.read'))->toBeTrue();
    expect(PermissionWildcard::matches('*.read', 'read'))->toBeFalse();
    expect(PermissionWildcard::matches('user_*', 'user_1'))->toBeTrue();
    expect(PermissionWildcard::matches('user_*', 'user_admin'))->toBeTrue();
    expect(PermissionWildcard::matches('user_*', 'admin_user'))->toBeFalse();
    expect(PermissionWildcard::matches('view.*.edit', 'view.posts.edit'))->toBeTrue();
    expect(PermissionWildcard::matches('view.*.edit', 'view.a.b.edit'))->toBeTrue();
    expect(PermissionWildcard::matches('view.*.edit', 'view.posts.delete'))->toBeFalse();
});

test('regex meta-characters in names are treated literally', function () {
    expect(PermissionWildcard::matches('view+1', 'view+1'))->toBeTrue();
    expect(PermissionWildcard::matches('view+1', 'viewa1'))->toBeFalse();
    expect(PermissionWildcard::matches('a.b', 'aXb'))->toBeFalse();
    expect(PermissionWildcard::matches('a(b)c', 'a(b)c'))->toBeTrue();
});

test('covers is strict and asymmetric', function () {
    expect(PermissionWildcard::covers('view.*', 'view.1'))->toBeTrue();
    expect(PermissionWildcard::covers('view.1', 'view.*'))->toBeFalse();
    expect(PermissionWildcard::covers('view.*', 'view.*'))->toBeFalse();
    expect(PermissionWildcard::covers('view.1', 'view.1'))->toBeFalse();
    expect(PermissionWildcard::covers('*', 'anything'))->toBeTrue();
    expect(PermissionWildcard::covers('*', '*'))->toBeFalse();
});

/*
 * ============================================================
 * Simplification
 * ============================================================
 */

test('simplify drops permissions covered by a sibling wildcard', function () {
    $permissions = collect([
        new Permission(['name' => 'view.*']),
        new Permission(['name' => 'view.1']),
        new Permission(['name' => 'view.2']),
        new Permission(['name' => 'edit.*']),
        new Permission(['name' => 'edit.1']),
    ]);

    $simplified = PermissionWildcard::simplify($permissions);

    expect($simplified)->toBeInstanceOf(Collection::class);
    expect($simplified->pluck('name')->sort()->values()->all())
        ->toEqual(['edit.*', 'view.*']);
});

test('simplify is a no-op when there are no wildcards', function () {
    $permissions = collect([
        new Permission(['name' => 'view.1']),
        new Permission(['name' => 'view.2']),
        new Permission(['name' => 'edit.1']),
    ]);

    $simplified = PermissionWildcard::simplify($permissions);

    expect($simplified->pluck('name')->sort()->values()->all())
        ->toEqual(['edit.1', 'view.1', 'view.2']);
});

test('simplify keeps a lone wildcard untouched', function () {
    $permissions = collect([
        new Permission(['name' => 'view.*']),
    ]);

    $simplified = PermissionWildcard::simplify($permissions);

    expect($simplified->pluck('name')->all())->toEqual(['view.*']);
});

test('simplify collapses a chain through a broader wildcard', function () {
    $permissions = collect([
        new Permission(['name' => '*']),
        new Permission(['name' => 'view.*']),
        new Permission(['name' => 'view.1']),
        new Permission(['name' => 'edit.x']),
    ]);

    $simplified = PermissionWildcard::simplify($permissions);

    expect($simplified->pluck('name')->all())->toEqual(['*']);
});

test('simplify ignores ordering of input', function () {
    $names = ['view.1', 'view.*', 'view.2'];

    foreach ([$names, array_reverse($names)] as $ordering) {
        $permissions = collect(array_map(fn ($n) => new Permission(['name' => $n]), $ordering));
        $simplified = PermissionWildcard::simplify($permissions);
        expect($simplified->pluck('name')->all())->toEqual(['view.*']);
    }
});

test('expand returns all matching permissions for simplified wildcards', function () {
    $simplified = collect([
        new Permission(['name' => 'view.*']),
        new Permission(['name' => 'edit.1']),
    ]);

    $allPermissions = collect([
        new Permission(['name' => 'view.*']),
        new Permission(['name' => 'view.1']),
        new Permission(['name' => 'view.2']),
        new Permission(['name' => 'edit.1']),
        new Permission(['name' => 'edit.2']),
    ]);

    $expanded = PermissionWildcard::expand($simplified, $allPermissions);

    expect($expanded->pluck('name')->sort()->values()->all())
        ->toEqual(['edit.1', 'view.1', 'view.2']);
});

test('expand removes wildcard permissions from its results', function () {
    $simplified = collect([
        new Permission(['name' => 'view.*']),
    ]);

    $allPermissions = collect([
        new Permission(['name' => 'view.*']),
        new Permission(['name' => 'view.1']),
        new Permission(['name' => 'view.2']),
    ]);

    $expanded = PermissionWildcard::expand($simplified, $allPermissions);

    expect($expanded->pluck('name')->sort()->values()->all())
        ->toEqual(['view.1', 'view.2']);
});

test('expand only returns exact matches when simplified contains literal permissions', function () {
    $simplified = collect([
        new Permission(['name' => 'view.1']),
    ]);

    $allPermissions = collect([
        new Permission(['name' => 'view.1']),
        new Permission(['name' => 'view.2']),
    ]);

    $expanded = PermissionWildcard::expand($simplified, $allPermissions);

    expect($expanded->pluck('name')->all())->toEqual(['view.1']);
});

test('expand defaults allPermissions to Permission::all()', function () {
    $simplified = collect([
        new Permission(['name' => 'view.*']),
    ]);

    Permission::create(['name' => 'view.*']);
    Permission::create(['name' => 'view.1']);
    Permission::create(['name' => 'view.2']);
    Permission::create(['name' => 'edit.1']);

    $expanded = PermissionWildcard::expand($simplified);

    expect($expanded->pluck('name')->sort()->values()->all())
        ->toEqual(['view.1', 'view.2']);
});

/*
 * ============================================================
 * getPermissionsOf simplifies inherited permissions
 * ============================================================
 */

test('getPermissionsOf collapses redundant permissions under a wildcard', function () {
    $root = Role::create(['name' => 'Root']);

    $wild = Permission::create(['name' => 'view.*']);
    $one = Permission::create(['name' => 'view.1']);
    $two = Permission::create(['name' => 'view.2']);
    $other = Permission::create(['name' => 'edit.posts']);

    $root->permissions()->attach([$wild->id, $one->id, $two->id, $other->id]);

    $user = User::create([
        'name' => 'Wild',
        'email' => 'wild@example.com',
        'password' => 'hash',
    ]);
    $user->roles()->attach($root);

    $permissions = RBAC::getPermissionsOf($user);

    expect($permissions)
        ->toHaveCount(2)
        ->toContainOnlyInstancesOf(Permission::class);
    expect($permissions->pluck('name')->sort()->values()->all())
        ->toEqual(['edit.posts', 'view.*']);
});

test('getPermissionsOf simplifies across the inherited hierarchy', function () {
    $root = Role::create(['name' => 'Root']);
    $child = Role::create(['name' => 'Child', 'parent_id' => $root->id]);

    $wild = Permission::create(['name' => 'view.*']);
    $specific = Permission::create(['name' => 'view.special']);

    // Wildcard sits on the parent, the specific sits on the child.
    $root->permissions()->attach($wild);
    $child->permissions()->attach($specific);

    $user = User::create([
        'name' => 'Inherit',
        'email' => 'inherit@example.com',
        'password' => 'hash',
    ]);
    $user->roles()->attach($root);

    $permissions = RBAC::getPermissionsOf($user);

    expect($permissions->pluck('name')->all())->toEqual(['view.*']);
});

test('HasRoles::permissions surfaces the simplified collection', function () {
    $root = Role::create(['name' => 'Root']);
    $wild = Permission::create(['name' => 'view.*']);
    $one = Permission::create(['name' => 'view.1']);
    $root->permissions()->attach([$wild->id, $one->id]);

    $user = User::create([
        'name' => 'Trait',
        'email' => 'trait@example.com',
        'password' => 'hash',
    ]);
    $user->roles()->attach($root);

    $permissions = $user->permissions();

    expect($permissions->pluck('name')->all())->toEqual(['view.*']);
});

/*
 * ============================================================
 * can() honours stored wildcards
 * ============================================================
 */

test('can returns true for names matched by a stored wildcard', function () {
    $root = Role::create(['name' => 'Root']);
    $wild = Permission::create(['name' => 'view.*']);
    $root->permissions()->attach($wild);

    $user = User::create([
        'name' => 'Holder',
        'email' => 'holder@example.com',
        'password' => 'hash',
    ]);
    $user->roles()->attach($root);

    expect(RBAC::can($user->id, 'view.1'))->toBeTrue();
    expect(RBAC::can($user->id, 'view.users.list'))->toBeTrue();
    expect(RBAC::can($user->id, 'view'))->toBeFalse();
    expect(RBAC::can($user->id, 'edit.1'))->toBeFalse();
});

test('can does not interpret the query string as a wildcard', function () {
    $root = Role::create(['name' => 'Root']);
    $one = Permission::create(['name' => 'view.1']);
    $root->permissions()->attach($one);

    $user = User::create([
        'name' => 'LiteralOnly',
        'email' => 'literal@example.com',
        'password' => 'hash',
    ]);
    $user->roles()->attach($root);

    expect(RBAC::can($user->id, 'view.1'))->toBeTrue();
    expect(RBAC::can($user->id, 'view.2'))->toBeFalse();
    // Querying with a wildcard does NOT match the literal view.1.
    expect(RBAC::can($user->id, 'view.*'))->toBeFalse();
});

test('can returns false when no permission is held', function () {
    $root = Role::create(['name' => 'Root']);

    $user = User::create([
        'name' => 'Empty',
        'email' => 'empty@example.com',
        'password' => 'hash',
    ]);
    $user->roles()->attach($root);

    expect(RBAC::can($user->id, 'view.1'))->toBeFalse();
    expect(RBAC::can($user->id, 'whatever'))->toBeFalse();
});

test('wildcard permissions are inherited downwards just like literal ones', function () {
    $root = Role::create(['name' => 'Root']);
    $child = Role::create(['name' => 'Child', 'parent_id' => $root->id]);

    $wild = Permission::create(['name' => 'view.*']);
    $root->permissions()->attach($wild);

    $user = User::create([
        'name' => 'RootUser',
        'email' => 'rootuser@example.com',
        'password' => 'hash',
    ]);
    $user->roles()->attach($root);

    // User in root inherits view.* from descendants? No — inheritance flows
    // the other way: permissions on descendants are gathered for users who
    // hold the ancestor. Here root *itself* owns view.*.
    expect(RBAC::can($user->id, 'view.1'))->toBeTrue();

    // A user on the child role does NOT have view.* (the ancestor's
    // permissions are not inherited upwards — the existing inheritance test
    // already documents this).
    $childUser = User::create([
        'name' => 'ChildUser',
        'email' => 'childuser@example.com',
        'password' => 'hash',
    ]);
    $childUser->roles()->attach($child);

    expect(RBAC::can($childUser->id, 'view.1'))->toBeFalse();
});

/*
 * ============================================================
 * getUsersWithPermission honours stored wildcards
 * ============================================================
 */

test('getUsersWithPermission finds holders of a stored wildcard', function () {
    $root = Role::create(['name' => 'Root']);
    $other = Role::create(['name' => 'Other', 'parent_id' => $root->id]);

    $wild = Permission::create(['name' => 'view.*']);
    $specific = Permission::create(['name' => 'view.1']);

    $root->permissions()->attach($wild);
    $other->permissions()->attach($specific);

    $wildHolder = User::create([
        'name' => 'WildHolder',
        'email' => 'wildholder@example.com',
        'password' => 'hash',
    ]);
    $wildHolder->roles()->attach($root);

    $specificHolder = User::create([
        'name' => 'SpecificHolder',
        'email' => 'specificholder@example.com',
        'password' => 'hash',
    ]);
    $specificHolder->roles()->attach($other);

    // Queried `view.1` is granted by both the wildcard and the literal.
    $usersForOne = RBAC::getUsersWithPermission('view.1');
    expect($usersForOne->pluck('id')->sort()->values()->all())
        ->toEqual(collect([$wildHolder->id, $specificHolder->id])->sort()->values()->all());

    // Queried `view.2` is granted only by the wildcard.
    $usersForTwo = RBAC::getUsersWithPermission('view.2');
    expect($usersForTwo->pluck('id')->all())->toEqual([$wildHolder->id]);

    // Queried `edit.1` matches no stored permission.
    expect(RBAC::getUsersWithPermission('edit.1'))->toBeEmpty();
});

test('getUsersWithPermission deduplicates users covered by multiple matches', function () {
    $root = Role::create(['name' => 'Root']);

    $wild = Permission::create(['name' => 'view.*']);
    $specific = Permission::create(['name' => 'view.1']);
    $root->permissions()->attach([$wild->id, $specific->id]);

    $user = User::create([
        'name' => 'OnlyOne',
        'email' => 'onlyone@example.com',
        'password' => 'hash',
    ]);
    $user->roles()->attach($root);

    $users = RBAC::getUsersWithPermission('view.1');
    expect($users)->toHaveCount(1);
    expect($users->first()->id)->toBe($user->id);
});

/*
 * ============================================================
 * Cache invalidation around wildcard permissions
 * ============================================================
 */

test('deleting a wildcard permission invalidates cached can() answers it granted', function () {
    $root = Role::create(['name' => 'Root']);
    $wild = Permission::create(['name' => 'view.*']);
    $root->permissions()->attach($wild);

    $user = User::create([
        'name' => 'CacheUser',
        'email' => 'cacheuser@example.com',
        'password' => 'hash',
    ]);
    $user->roles()->attach($root);

    // Prime the cache for two distinct literal queries.
    expect(RBAC::can($user->id, 'view.1'))->toBeTrue();
    expect(RBAC::can($user->id, 'view.2'))->toBeTrue();

    // Delete the wildcard. The PermissionObserver must invalidate the user
    // caches; the cached `view.1` and `view.2` entries (tagged only by their
    // literal queried names) would otherwise survive.
    $wild->delete();

    expect(RBAC::can($user->id, 'view.1'))->toBeFalse();
    expect(RBAC::can($user->id, 'view.2'))->toBeFalse();
});

test('renaming a wildcard permission invalidates cached can() answers it granted', function () {
    $root = Role::create(['name' => 'Root']);
    $wild = Permission::create(['name' => 'view.*']);
    $root->permissions()->attach($wild);

    $user = User::create([
        'name' => 'RenameUser',
        'email' => 'renameuser@example.com',
        'password' => 'hash',
    ]);
    $user->roles()->attach($root);

    expect(RBAC::can($user->id, 'view.1'))->toBeTrue();

    // Rename the wildcard so it no longer covers `view.1`.
    $wild->name = 'edit.*';
    $wild->save();

    expect(RBAC::can($user->id, 'view.1'))->toBeFalse();
    expect(RBAC::can($user->id, 'edit.1'))->toBeTrue();
});

test('manual invalidatePermissionCache on a wildcard drops user-level cached entries', function () {
    $root = Role::create(['name' => 'Root']);
    $wild = Permission::create(['name' => 'view.*']);
    $root->permissions()->attach($wild);

    $user = User::create([
        'name' => 'ManualUser',
        'email' => 'manualuser@example.com',
        'password' => 'hash',
    ]);
    $user->roles()->attach($root);

    // Prime the cache.
    expect(RBAC::can($user->id, 'view.1'))->toBeTrue();

    // A bare second call must be a cache hit (no queries).
    DB::enableQueryLog();
    expect(RBAC::can($user->id, 'view.1'))->toBeTrue();
    expect(DB::getQueryLog())->toBeEmpty();

    // Manually invalidate the wildcard permission. Because nothing about the
    // permission has actually changed, can() must still return true — but the
    // entry must have been flushed, so the next call has to recompute (i.e.
    // it triggers DB queries).
    DB::flushQueryLog();
    RBAC::invalidatePermissionCache('view.*');

    DB::flushQueryLog();
    expect(RBAC::can($user->id, 'view.1'))->toBeTrue();
    expect(DB::getQueryLog())->not->toBeEmpty();
});
