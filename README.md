# simpleHRBAC

**Simple Hierarchical Role-Based Access Control** for Laravel using MySQL/MariaDB.

[![License: GPL-3.0](https://img.shields.io/badge/License-GPL--3.0-blue.svg)](LICENSE)

## Overview

simpleHRBAC is a lightweight Laravel package that implements **Hierarchical Role-Based Access Control (HRBAC)**. It extends standard RBAC by organizing roles in a **tree hierarchy**, where permissions assigned to parent roles are automatically inherited by all descendant (child) roles.

The package uses the **Closure Table** pattern to efficiently store and query role hierarchy relationships, avoiding expensive recursive queries or CTEs.

### Key Features

- **Role hierarchy** with automatic permission inheritance
- **Closure Table** pattern for efficient tree queries
- **Wildcard permissions** — store `view.*` to grant any `view.…` name
- **Automatic caching** with Laravel Cache tags and observer-driven invalidation
- **JSON import** for bulk seeding and data migration
- **Interactive shell** — browse and manage the role tree with `php artisan rbac:shell`
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
            "url": "https://github.com/DSE-Western-Thessaloniki/simpleHRBAC.git"
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

### 4. Wildcard Permissions

A stored permission name may contain `*` as a wildcard. `view.*` grants any
name that matches it (`view.1`, `view.users.list`, …):

```php
$role->permissions()->attach(Permission::create(['name' => 'view.*']));

RBAC::can($user->id, 'view.1');           // true
RBAC::can($user->id, 'view.users.list');  // true
RBAC::can($user->id, 'edit.1');           // false
```

`RBAC::getPermissionsOf($user)` returns a **simplified** collection: any
permission that is already covered by another in the same collection is
dropped, so `view.*` + `view.1` + `view.2` collapses to just `view.*`.

`*` is greedy (matches across dots) and may appear anywhere in the name.
See [doc/description.md](doc/description.md#wildcard-permissions) for the full
semantics, simplification rules, and cache behavior.

## Interactive Shell

The package ships an interactive console for browsing and managing the role
tree straight from the terminal:

```bash
php artisan rbac:shell
```

The shell starts at the **root role** (the role with no parent) and shows the
current path in the prompt, e.g. `simpleHRBAC [Root/Managers] >`. Use `cd` to
move around the tree just like a file system; `ls` shows the children of the
current role together with their users and permissions.

A session might look like this:

```text
simpleHRBAC [/] > add role
 ? Role name ................................................................ Managers
 ? Description (optional) ........................................... Manages the sales team
Role #2 "Managers" created.

simpleHRBAC [Root] > cd Ma<TAB>
simpleHRBAC [Root/Managers] > add permission --new
 ? Permission name ........................................................ posts.edit
 ? Description (optional) ............................................. Edit blog posts
Permission #3 "posts.edit" created and attached to role #2 "Managers".

simpleHRBAC [Root/Managers] > rm role Nowhere
 Role "Nowhere" was not found.

simpleHRBAC [Root/Managers] > help
simpleHRBAC [Root/Managers] > exit
Goodbye!
```

### Shell Commands

| Command | Usage | Description |
|---|---|---|
| `ls` | `ls` | List the child roles of the current role with their users (`id: name`) and permissions (`id: name — description`) |
| `cd` | `cd <role>` | Change the current role by **id or name**; `..` goes one level up, `/` jumps to the root |
| `add role` | `add role` | Create a role under the current one — unique name (required) and optional description |
| `add permission` | `add permission [--new]` | Attach existing permissions to the current role (multi-select), or create a new unique one and attach it with `--new` |
| `add user` | `add user` | Grant the current role to the selected users (multi-select) |
| `mv` | `mv <role> <path>` | Move a role under a new parent, given by id, name, or a slash path from the root (`Root/Managers`) |
| `rm role` | `rm role <role>` | Delete a role — only allowed when it has **no children and no users** (the root can never be deleted) |
| `rm user` | `rm user <user>` | Remove the current role from the given user |
| `rm permission` | `rm permission <permission>` | Detach the given permission from the current role |
| `help` | `help` | Show the command list |
| `exit` | `exit` / `quit` | Leave the shell (`Ctrl+D` on an empty line works too) |

Arguments written as `<role>`, `<user>` or `<permission>` accept either the
**id** or the **name** of the record; duplicate user names are disambiguated
with a selection prompt. Wrap names containing spaces in quotes
(`cd "Senior Managers"`) — the `add` prompts accept spaces directly.

### Tab Completion

When PHP's `ext-readline` extension is available (suggested, see
`composer.json`), the shell provides context-aware **TAB completion**:

- commands (`ls`, `cd`, `add`, `rm`, `mv`, `help`, `exit`, `quit`)
- role ids and names after `cd`, `mv` and `rm role`
- full role paths from the root as the second argument of `mv` (`Root/Man…`)
- `role` / `user` / `permission` after `rm`, plus matching ids and names after that
- `role` / `permission [--new]` / `user` after `add`

Without `ext-readline` (e.g. on Windows) the shell falls back to plain line
input and simply reads one command per line — everything else keeps working.

### User Model

All user listings and selections use the model configured as
`simple-hrbac.user_model` (see [Publish Config and Migrations](#publish-config-and-migrations)),
so the shell works with whatever user model your application uses.

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
