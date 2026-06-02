# simpleHRBAC - Simple Hierarchical Role-Based Access Control

> **Last Updated**: June 2, 2026

## Overview

**simpleHRBAC** is a lightweight Laravel package that implements **Hierarchical Role-Based Access Control (HRBAC)** using MySQL/MariaDB. It extends standard RBAC by organizing roles in a **tree hierarchy**, where permissions assigned to parent roles are automatically inherited by all descendant (child) roles.

The package uses the **Closure Table** pattern to efficiently store and query role hierarchy relationships, avoiding expensive recursive queries or CTEs (Common Table Expressions).

---

## Key Features

### 1. Hierarchical Role Management
- Roles are organized in a **tree structure** with a single root node
- **Permission inheritance**: child roles automatically inherit permissions from all ancestor roles
- **Closure Table pattern**: all ancestor-descendant relationships are explicitly stored in a `role_tree` table, enabling efficient hierarchy queries

### 2. Core Components

| Component | Description |
|-----------|-------------|
| **Role Model** | Represents a role in the hierarchy. Supports parent-child relationships and tree operations (children, parents, move, etc.) |
| **Permission Model** | Represents a discrete permission that can be assigned to roles |
| **RoleUser Pivot Model** | Pivot model linking users to roles, with cache invalidation on changes |
| **RBACService** | Central service for permission checking: `getPermissionsOf()`, `can()`, `canWithoutCache()`, `getUsersWithPermission()`, and cache invalidation methods |
| **HasRoles Trait** | Applied to the User model to enable role/permission functionality |
| **ClosureTable Helper** | Manages the closure table with query builder for efficient tree operations |
| **DataHelper** | Supports bulk import of permissions, roles, and users from JSON files or arrays |
| **RBAC Facade** | Provides a clean static API to the RBACService |
| **RoleObserver** | Enforces tree integrity constraints and invalidates cache during role lifecycle events |
| **PermissionObserver** | Invalidates permission cache when names change or permissions are deleted; flushes the affected users' caches when wildcard permissions change |
| **RoleUserObserver** | Invalidates user cache when roles are attached or detached |
| **RoleFactory / PermissionFactory** | Eloquent factories for testing and seeding |
| **PermissionWildcard Helper** | Matches and simplifies permission names that contain `*` |
| **RBACException** | Custom exception class for RBAC errors |

### 3. Database Schema

The package creates 5 tables:

| Table | Purpose |
|-------|---------|
| `permissions` | Stores permission records (id, name) |
| `roles` | Stores role records (id, name, description, parent_id) |
| `role_tree` | Closure table storing all ancestor-descendant relationships (parent, child, depth) |
| `permission_role` | Pivot table linking permissions to roles |
| `role_user` | Pivot table linking users to roles |

---

## How It Works

### Permission Inheritance

When checking a user's permissions:

1. The system retrieves all roles directly assigned to the user
2. For each role, it collects permissions from:
   - The role itself
   - **All descendant roles** in the hierarchy (children, grandchildren, etc.)
3. The result is a unique collection of all permissions the user has through their role hierarchy

Example hierarchy:
```
Root (no direct permissions)
├── Manager (permission: "manage_team")
│   ├── Team Lead (permission: "review_work")
│   └── Senior Developer (permission: "code_review")
└── Developer (permission: "write_code")
```

A user assigned to the `Root` role would have: `manage_team`, `review_work`, `code_review`, `write_code`

### Closure Table Pattern

The `role_tree` table stores every ancestor-descendant relationship explicitly:

| parent | child | depth |
|--------|-------|-------|
| 1 | 1 | 0 |
| 1 | 2 | 1 |
| 1 | 3 | 1 |
| 2 | 2 | 0 |
| 2 | 4 | 1 |
| ... | ... | ... |

This allows queries like "get all descendants of role X" without recursive CTEs.

### RoleObserver Constraints

- **Single root**: Only one role can have `parent_id = null`
- **No self-parenting**: A role cannot be its own parent
- **Root immutability**: The root role cannot be moved or deleted
- **Cascading reparenting**: When a role is deleted, its children are re-parented to the deleted role's parent

### Memoization and Caching

The `can()` method uses Laravel's **Cache with Tags** for efficient, persistent permission caching:

- **Cache Key**: `rbac:can:{userId}:{permission}`
- **Cache Tags**: Two tags per entry — `rbac:user:{userId}` and `rbac:permission:{permission}` — enabling targeted invalidation
- **Fallback**: If the cache driver doesn't support tags (e.g., file driver), it falls back to `canWithoutCache()`

**Cache Invalidation** is handled automatically by observers:

| Event | Observer | Action |
|-------|----------|--------|
| Role created/updated/deleted | `RoleObserver` | Invalidates cache for all users assigned to the role or its descendants |
| Permission updated/deleted | `PermissionObserver` | Invalidates cache for the specific permission |
| Role attached/detached from user | `RoleUserObserver` | Invalidates cache for the specific user |

**Manual Cache Invalidation** methods are also available:

```php
use Dsewth\SimpleHRBAC\Facades\RBAC;

// Invalidate cache for a specific user
RBAC::invalidateUserCache($userId);

// Invalidate cache for a specific permission
RBAC::invalidatePermissionCache('manage_team');

// Invalidate all RBAC cache
RBAC::invalidateAllCache();
```

---

## Installation

```bash
composer require dsewth/simple-hrbac
```

Publish the config and migrations:

```bash
php artisan vendor:publish --provider="Dsewth\SimpleHRBAC\Providers\SimpleHRBACServiceProvider"
```

---

## Usage

### 1. Add the HasRoles Trait to Your User Model

```php
namespace App\Models;

use Dsewth\SimpleHRBAC\Traits\HasRoles;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasRoles;
    // ...
}
```

### 2. Create Roles and Permissions

```php
use Dsewth\SimpleHRBAC\Models\Role;
use Dsewth\SimpleHRBAC\Models\Permission;

// Create root role (must be created first, only one allowed)
$root = Role::create(['name' => 'Root']);

// Create child roles
$manager = Role::create([
    'name' => 'Manager',
    'parent_id' => $root->id,
]);

// Create permissions
$manageTeam = Permission::create(['name' => 'manage_team']);

// Assign permissions to roles
$manager->permissions()->attach($manageTeam);

// Assign roles to users
$user->roles()->attach($manager);
```

### 3. Check Permissions

```php
use Dsewth\SimpleHRBAC\Facades\RBAC;

// Check if a user has a permission (cached automatically)
if (RBAC::can($user->id, 'manage_team')) {
    // ...
}

// Or using the HasRoles trait method
if ($user->canUsingRBAC('manage_team')) {
    // ...
}

// Get all permissions of a user
$permissions = RBAC::getPermissionsOf($user);
// Or
$permissions = $user->permissions();

// Get all users with a specific permission
$users = RBAC::getUsersWithPermission('manage_team');
```

### 3b. Cache Invalidation

Permissions are cached automatically. The cache is invalidated automatically when:
- Roles are created, updated, or deleted (via `RoleObserver`)
- Permissions are updated or deleted (via `PermissionObserver`)
- Roles are attached/detached from users (via `RoleUserObserver`)

For manual control:

```php
use Dsewth\SimpleHRBAC\Facades\RBAC;

// Invalidate cache for a specific user
RBAC::invalidateUserCache($userId);

// Invalidate cache for a specific permission
RBAC::invalidatePermissionCache('manage_team');

// Invalidate all cache (use sparingly)
RBAC::invalidateAllCache();
```

### 4. Role Hierarchy Operations

```php
use Dsewth\SimpleHRBAC\Models\Role;

$role = Role::find(2);

// Get all descendants (children, grandchildren, etc.)
$children = $role->children();

// Get immediate children only
$immediate = $role->immediateChildren();

// Get all ancestors
$parents = $role->parents();

// Get direct parent
$parent = $role->parent()->first();

// Move a role to a new parent
$role->parent_id = 3;
$role->save(); // Closure table is updated automatically

// Delete a role (children are re-parented)
$role->delete();
```

### 5. Import Data from JSON

```php
use Dsewth\SimpleHRBAC\Helpers\DataHelper;

// Import from a JSON file
DataHelper::importJsonFile('/path/to/data.json');

// Or import from an array
DataHelper::importData([
    'Permissions' => [
        ['id' => 1, 'name' => 'manage_team'],
        ['id' => 2, 'name' => 'review_work'],
    ],
    'Roles' => [
        ['id' => 1, 'name' => 'Root'],
        ['id' => 2, 'name' => 'Manager', 'parent_id' => 1, 'permissions' => [1]],
    ],
    'Users' => [
        ['id' => 1, 'name' => 'John', 'roles' => [2]],
    ],
]);
```

---

## Wildcard Permissions

A stored permission name may contain `*` as a wildcard. When such a permission
is held by a user (directly or through their role hierarchy), every name the
pattern matches is granted.

```php
$role->permissions()->attach(Permission::create(['name' => 'view.*']));

RBAC::can($user->id, 'view.1');          // true
RBAC::can($user->id, 'view.users.list'); // true
RBAC::can($user->id, 'edit.1');          // false
```

### Matching rules

- `*` is **greedy**: it matches any sequence of characters, including dots.
  `view.*` matches `view.1`, `view.users.list`, `view.a.b.c`.
- `*` may appear **anywhere** in the name, and the name may contain more than
  one `*`:
  - `*.read` matches `posts.read`, `users.admin.read`
  - `user_*` matches `user_1`, `user_admin`
  - `view.*.edit` matches `view.posts.edit`, `view.a.b.edit`
  - `*` alone matches any name (use with care)
- Every other character is treated **literally**, including regex
  meta-characters such as `.`, `+`, `(`, `)`.
- The wildcard semantics applies **only to stored permission names**. Names
  passed to `RBAC::can()` and `RBAC::getUsersWithPermission()` are treated as
  literal strings — `RBAC::can($u, 'view.*')` only returns true if the user
  holds a permission literally named `view.*`.

### Simplification

`RBAC::getPermissionsOf($user)` (and therefore `$user->permissions()` from the
`HasRoles` trait) returns a **simplified** collection: any permission whose
name is already covered by another permission in the same collection is
omitted.

If a user inherits `view.*`, `view.1` and `view.2`, the returned collection
contains only `view.*`. Unrelated wildcards survive — `view.*` and `edit.*`
both appear in the result.

Two permissions with the same name are considered duplicates and only one is
returned. The simplification is applied after permissions from all of the
user's roles and their descendants have been gathered.

You can also call the helper directly:

```php
use Dsewth\SimpleHRBAC\Helpers\PermissionWildcard;

PermissionWildcard::matches('view.*', 'view.1');      // true
PermissionWildcard::covers('view.*', 'view.1');       // true (strict)
PermissionWildcard::covers('view.1', 'view.1');       // false (equal, not covering)
PermissionWildcard::simplify($collectionOfPermissions);
```

### `getUsersWithPermission()` with wildcards

`RBAC::getUsersWithPermission('view.1')` returns every user whose effective
permissions match the queried name — including users whose role holds a
wildcard like `view.*` that covers it. Users granted by multiple matching
permissions are returned only once.

### Cache invalidation

Cached `can()` answers are keyed by the literal queried name (`view.1`), not
by the wildcard that may have granted them (`view.*`). When a wildcard
permission is renamed or deleted, the `PermissionObserver` therefore flushes
the **per-user** caches of every user that held it (directly or through their
role hierarchy), in addition to flushing the permission-name tag.

`RBAC::invalidatePermissionCache('view.*')` does the same thing for manual
invalidation: if the named permission is a wildcard pattern and still exists
in the database, the affected users' caches are flushed.

---

## Configuration

The package provides a single configuration option in `config/simple-hrbac.php`:

```php
return [
    'user_model' => \App\Models\User::class,
];
```

---

## Requirements

- PHP ^8.2
- Laravel ^11.0 | ^12.0 | ^13.0
- MySQL/MariaDB

---

## License

GPL-3.0-or-later

## Author

Theofilos Intzoglou (int.teo@gmail.com)
