<?php

use Dsewth\SimpleHRBAC\Console\ShellSession;
use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Output\BufferedOutput;
use Workbench\App\Models\User;

/**
 * Queue the key presses that type $value into a prompt and press enter.
 */
function shell_keys(string $value): array
{
    $keys = str_split($value);
    $keys[] = "\n";

    return $keys;
}

/**
 * Disable the laravel/prompts fallback (registered by artisan commands run
 * during the tests, e.g. migrations) and fake the terminal with $keys so the
 * prompts read exactly the queued key presses.
 */
function shell_prompt(array $keys): void
{
    $fallback = new ReflectionProperty(Prompt::class, 'shouldFallback');
    $fallback->setAccessible(true);
    $fallback->setValue(null, false);

    Prompt::fake($keys);
}

function shell_role(string $name, ?Role $parent = null, ?string $description = null): Role
{
    return Role::create([
        'name' => $name,
        'description' => $description,
        'parent_id' => $parent?->id,
    ]);
}

beforeAll(function () {
    DataHelper::useUserModel(User::class);
});

beforeEach(function () {
    // Force the lazy database migrations to run before prompts are faked.
    Role::query()->count();

    $this->output = new BufferedOutput;
    $this->session = new ShellSession($this->output);

    shell_prompt([]);
});

it('registers the rbac:shell command', function () {
    expect(Artisan::all())->toHaveKey('rbac:shell');
});

it('starts on an empty tree and explains how to create the root', function () {
    $this->session->execute('ls');

    $output = $this->output->fetch();

    expect($output)
        ->toContain('There are no roles yet')
        ->toContain('add role');
    expect($this->session->path())->toBe('/');
    expect($this->session->prompt())->toBe('simpleHRBAC [/] > ');
});

it('creates the root role with add role', function () {
    shell_prompt([...shell_keys('Root'), ...shell_keys('Top level roles')]);

    $this->session->execute('add role');

    $root = Role::query()->whereNull('parent_id')->first();

    expect($root)->not->toBeNull();
    expect($root->name)->toBe('Root');
    expect($root->description)->toBe('Top level roles');
    expect($this->output->fetch())->toContain('Role #'.$root->id.' "Root" created.');
    expect($this->session->path())->toBe('Root');
    expect($this->session->prompt())->toBe('simpleHRBAC [Root] > ');
});

it('creates a role without a description', function () {
    shell_role('Root');

    shell_prompt([...shell_keys('Managers'), "\n"]);

    $this->session->execute('add role');

    $managers = Role::query()->where('name', 'Managers')->first();

    expect($managers)->not->toBeNull();
    expect($managers->description)->toBeNull();
    expect($managers->parent_id)->toBe(Role::query()->where('name', 'Root')->first()->id);
});

it('rejects a duplicate role name', function () {
    shell_role('Root');

    // Type "Root" (rejected), clear the field and type "Managers" instead.
    shell_prompt([
        ...shell_keys('Root'),
        ...array_fill(0, 4, "\x7f"),
        ...shell_keys('Managers'),
        "\n",
    ]);

    $this->session->execute('add role');

    Prompt::assertStrippedOutputContains('already exists');
    expect(Role::query()->where('name', 'Root')->count())->toBe(1);
    expect(Role::query()->where('name', 'Managers')->exists())->toBeTrue();
});

it('lists child roles with their users and permissions', function () {
    $root = shell_role('Root');
    $managers = shell_role('Managers', $root, 'Management team');

    $permission = Permission::create(['name' => 'users.view', 'description' => 'View users']);
    $managers->permissions()->attach($permission->id);

    $user = User::factory()->create(['name' => 'Alice']);
    $managers->users()->attach($user->id);

    $this->session->execute('ls');

    $output = $this->output->fetch();

    expect($output)
        ->toContain('Managers')
        ->toContain('Management team')
        ->toContain('users.view')
        ->toContain('View users')
        ->toContain($user->id.': Alice');
});

it('navigates the tree with cd', function () {
    $root = shell_role('Root');
    $managers = shell_role('Managers', $root);
    $sales = shell_role('Sales', $managers);

    $this->session->execute('cd Managers');
    expect($this->session->path())->toBe('Root/Managers');

    $this->session->execute('cd '.$sales->id);
    expect($this->session->path())->toBe('Root/Managers/Sales');

    $this->session->execute('cd ..');
    expect($this->session->path())->toBe('Root/Managers');

    $this->session->execute('cd /');
    expect($this->session->path())->toBe('Root');

    $this->session->execute('cd Nowhere');
    expect($this->output->fetch())->toContain('Role "Nowhere" was not found');
});

it('attaches existing permissions with add permission', function () {
    $root = shell_role('Root');

    Permission::create(['name' => 'alpha.view', 'description' => 'View alpha']);
    Permission::create(['name' => 'beta.view', 'description' => null]);
    $root->permissions()->attach(Permission::query()->where('name', 'alpha.view')->first()->id);

    shell_prompt([' ', "\n"]);
    $this->session->execute('add permission');

    $names = $root->permissions()->pluck('permissions.name')->all();

    expect($this->output->fetch())->toContain('Attached 1 permission(s)');
    expect($names)->toHaveCount(2);
    expect($names)->toContain('beta.view');
});

it('reports when every permission is already attached', function () {
    $root = shell_role('Root');
    $permission = Permission::create(['name' => 'alpha.view', 'description' => null]);
    $root->permissions()->attach($permission->id);

    $this->session->execute('add permission');

    expect($this->output->fetch())->toContain('Every permission is already attached');
});

it('creates and attaches a new permission with add permission --new', function () {
    shell_role('Root');

    shell_prompt([...shell_keys('posts.edit'), ...shell_keys('Edit posts')]);
    $this->session->execute('add permission --new');

    $permission = Permission::query()->where('name', 'posts.edit')->first();

    expect($permission)->not->toBeNull();
    expect($permission->description)->toBe('Edit posts');
    expect(Role::first()->permissions()->pluck('permissions.id')->all())->toBe([$permission->id]);
    expect($this->output->fetch())->toContain('created and attached');
});

it('rejects a duplicate permission name', function () {
    shell_role('Root');
    Permission::create(['name' => 'posts.edit', 'description' => null]);

    // Type "posts.edit" (rejected), clear the field and type "posts.delete" instead.
    shell_prompt([
        ...shell_keys('posts.edit'),
        ...array_fill(0, 10, "\x7f"),
        ...shell_keys('posts.delete'),
        "\n",
    ]);
    $this->session->execute('add permission --new');

    Prompt::assertStrippedOutputContains('already exists');
    expect(Permission::query()->where('name', 'posts.delete')->exists())->toBeTrue();
});

it('grants the role to selected users with add user', function () {
    shell_role('Root');
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    shell_prompt([' ', "\n"]);
    $this->session->execute('add user');

    expect($this->output->fetch())->toContain('granted to 1 user(s)');
    expect($alice->roles()->pluck('roles.id')->all())->toBe([Role::first()->id]);
    expect($bob->roles()->count())->toBe(0);
});

it('reports when all users already have the role', function () {
    $root = shell_role('Root');
    $alice = User::factory()->create(['name' => 'Alice']);
    $root->users()->attach($alice->id);

    $this->session->execute('add user');

    expect($this->output->fetch())->toContain('All users already have this role');
});

it('moves roles by name, id and path', function () {
    $root = shell_role('Root');
    $managers = shell_role('Managers', $root);
    $sales = shell_role('Sales', $managers);
    $operations = shell_role('Operations', $root);

    $this->session->execute('mv Sales Root');
    expect($sales->fresh()->parent_id)->toBe($root->id);

    $this->session->execute('mv Sales Root/Operations');
    expect($sales->fresh()->parent_id)->toBe($operations->id);

    $this->session->execute('mv '.$sales->id.' '.$managers->id);
    expect($sales->fresh()->parent_id)->toBe($managers->id);

    $this->session->execute('mv Sales Root/Managers');

    $output = $this->output->fetch();

    expect($output)
        ->toContain('Moved role')
        ->toContain('is already under');
});

it('refuses invalid moves', function () {
    $root = shell_role('Root');
    $managers = shell_role('Managers', $root);
    $sales = shell_role('Sales', $managers);

    $this->session->execute('mv Root Managers');
    expect($this->output->fetch())->toContain('root role cannot be moved');

    $this->session->execute('mv Managers Root/Managers/Sales');
    expect($this->output->fetch())->toContain('own descendants');

    $this->session->execute('mv Sales Sales');
    expect($this->output->fetch())->toContain('under itself');

    $this->session->execute('mv Sales Root/Nowhere');
    expect($this->output->fetch())->toContain('was not found');

    expect($sales->fresh()->parent_id)->toBe($managers->id);
});

it('refuses to delete roles that have children or users', function () {
    $root = shell_role('Root');
    $managers = shell_role('Managers', $root);
    shell_role('Sales', $managers);
    $leaf = shell_role('Leaf', $root);

    $user = User::factory()->create(['name' => 'Alice']);
    $leaf->users()->attach($user->id);

    $this->session->execute('rm role Managers');
    expect($this->output->fetch())->toContain('still has 1 child role(s)');

    $this->session->execute('rm role Leaf');
    expect($this->output->fetch())->toContain('still assigned to 1 user(s)');

    $this->session->execute('rm role Root');
    expect($this->output->fetch())->toContain('root role cannot be deleted');

    $this->session->execute('rm role Nowhere');
    expect($this->output->fetch())->toContain('was not found');

    expect(Role::query()->count())->toBe(4);
});

it('deletes leaf roles and follows the current directory to the parent', function () {
    $root = shell_role('Root');
    shell_role('Temp', $root);

    $this->session->execute('cd Temp');
    expect($this->session->path())->toBe('Root/Temp');

    $this->session->execute('rm role Temp');

    $output = $this->output->fetch();

    expect($output)->toContain('deleted');
    expect(Role::query()->where('name', 'Temp')->exists())->toBeFalse();
    expect($this->session->path())->toBe('Root');
});

it('removes users and permissions from the current role', function () {
    $root = shell_role('Root');
    $permission = Permission::create(['name' => 'alpha.view', 'description' => null]);
    $root->permissions()->attach($permission->id);

    $alice = User::factory()->create(['name' => 'Alice']);
    $root->users()->attach($alice->id);

    $this->session->execute('rm permission alpha.view');
    expect($this->output->fetch())->toContain('detached from role');
    expect($root->permissions()->count())->toBe(0);

    $this->session->execute('rm permission alpha.view');
    expect($this->output->fetch())->toContain('not attached');

    $this->session->execute('rm permission 999');
    expect($this->output->fetch())->toContain('was not found');

    $this->session->execute('rm user Alice');
    expect($this->output->fetch())->toContain('no longer has role');
    expect($root->users()->count())->toBe(0);

    $this->session->execute('rm user Alice');
    expect($this->output->fetch())->toContain('does not have role');
});

it('asks which user to remove when names are duplicated', function () {
    $root = shell_role('Root');
    $first = User::factory()->create(['name' => 'Bob']);
    $second = User::factory()->create(['name' => 'Bob']);
    $root->users()->attach([$first->id, $second->id]);

    shell_prompt(["\n"]);
    $this->session->execute('rm user Bob');

    expect($this->output->fetch())->toContain('no longer has role');
    expect($root->users()->pluck('users.id')->all())->toBe([$second->id]);
});

it('presents the command list with help', function () {
    $this->session->execute('help');

    $output = $this->output->fetch();

    expect($output)
        ->toContain('cd <role>')
        ->toContain('add permission [--new]')
        ->toContain('mv <role> <path>')
        ->toContain('rm role <role>')
        ->toContain('TAB');
});

it('leaves the shell with exit and quit', function () {
    expect($this->session->execute('exit'))->toBeFalse();
    expect($this->session->execute('quit'))->toBeFalse();
    expect($this->session->execute(''))->toBeTrue();
    expect($this->session->execute('   '))->toBeTrue();
});

it('reports unknown commands', function () {
    $this->session->execute('frobnicate');

    expect($this->output->fetch())->toContain('Unknown command "frobnicate"');
});

it('provides context aware tab completion', function () {
    $root = shell_role('Root');
    shell_role('Managers', $root);
    shell_role('Sales', $root);
    Permission::create(['name' => 'alpha.view', 'description' => null]);
    User::factory()->create(['name' => 'Alice']);

    $completer = $this->session->completer();

    expect($completer->complete(''))
        ->toContain('ls', 'cd', 'add', 'rm', 'mv', 'help', 'exit', 'quit');
    expect($completer->complete('cd Ma'))->toBe(['Managers']);
    expect($completer->complete('cd '))->toContain('Root', 'Managers', 'Sales', '..', '/');
    expect($completer->complete('rm '))->toBe(['role', 'user', 'permission']);
    expect($completer->complete('rm role '))->toContain('Managers');
    expect($completer->complete('rm user '))->toContain('Alice');
    expect($completer->complete('rm permission '))->toContain('alpha.view');
    expect($completer->complete('add '))->toBe(['role', 'permission', 'user']);
    expect($completer->complete('add permission '))->toBe(['--new']);
    expect($completer->complete('mv Sales Root/Man'))->toContain('Root/Managers');
    expect($completer->complete('help '))->toContain('ls');
    expect($completer->complete('ls --'))->toBe([]);
});
