# simpleHRBAC - Simple Hierarchical Role-Based Access Control

> **Last Updated**: April 11, 2026

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
| **PermissionObserver** | Invalidates permission cache when names change or permissions are deleted |
| **RoleUserObserver** | Invalidates user cache when roles are attached or detached |
| **RoleFactory / PermissionFactory** | Eloquent factories for testing and seeding |
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
$parent = $role->parent();

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
