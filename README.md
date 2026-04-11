# simpleHRBAC

**Simple Hierarchical Role-Based Access Control** for Laravel using MySQL/MariaDB.

[![License: GPL-3.0](https://img.shields.io/badge/License-GPL--3.0-blue.svg)](LICENSE)

## Overview

simpleHRBAC is a lightweight Laravel package that implements **Hierarchical Role-Based Access Control (HRBAC)**. It extends standard RBAC by organizing roles in a **tree hierarchy**, where permissions assigned to parent roles are automatically inherited by all descendant (child) roles.

The package uses the **Closure Table** pattern to efficiently store and query role hierarchy relationships, avoiding expensive recursive queries or CTEs.

### Key Features

- **Role hierarchy** with automatic permission inheritance
- **Closure Table** pattern for efficient tree queries
- **Automatic caching** with Laravel Cache tags and observer-driven invalidation
- **JSON import** for bulk seeding and data migration
- **Configurable user model** — no hard dependency on `\App\Models\User`

## Installation

### Via Composer (Packagist)

```bash
composer require dsewth/simple-hrbac
```

### Directly from GitHub

Add the repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/dsewth/simpleHRBAC.git"
        }
    ]
}
```

Then require the package using a **version tag**:

```bash
composer require dsewth/simple-hrbac:v1.2
```

Or install directly from the **main** branch:

```bash
composer require dsewth/simple-hrbac:dev-main
```

### Publish Config and Migrations

```bash
php artisan vendor:publish --provider="Dsewth\SimpleHRBAC\Providers\SimpleHRBACServiceProvider"
```

Run the migrations:

```bash
php artisan migrate
```

## Quick Start

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

// Create root role (only one allowed)
$root = Role::create(['name' => 'Root']);

// Create child roles
$manager = Role::create([
    'name' => 'Manager',
    'parent_id' => $root->id,
]);

// Create and assign permissions
$perm = Permission::create(['name' => 'manage_team']);
$manager->permissions()->attach($perm);

// Assign roles to users
$user->roles()->attach($manager);
```

### 3. Check Permissions

```php
use Dsewth\SimpleHRBAC\Facades\RBAC;

// Check permission (cached automatically)
if (RBAC::can($user->id, 'manage_team')) {
    // ...
}

// Or via the User model
if ($user->canUsingRBAC('manage_team')) {
    // ...
}

// Get all permissions of a user
$permissions = RBAC::getPermissionsOf($user);

// Get all users with a specific permission
$users = RBAC::getUsersWithPermission('manage_team');
```

## Documentation

For detailed documentation including the full API, database schema, caching behavior, and advanced usage, see [doc/description.md](doc/description.md).

## Requirements

- PHP ^8.2
- Laravel ^11.0 | ^12.0 | ^13.0
- MySQL/MariaDB

## License

GPL-3.0-or-later

## Author

Theofilos Intzoglou ([int.teo@gmail.com](mailto:int.teo@gmail.com))
