<?php

namespace Dsewth\SimpleHRBAC\Console;

use Dsewth\SimpleHRBAC\Exceptions\RBACException;
use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Holds the state (current role) and implements every command of the
 * interactive `rbac:shell` prompt.
 */
class ShellSession
{
    /**
     * All commands understood by the shell, used by `help` and by completion.
     *
     * @var list<string>
     */
    public const COMMANDS = ['ls', 'cd', 'add', 'rm', 'mv', 'help', 'exit', 'quit'];

    protected ?Role $current = null;

    protected bool $loaded = false;

    public function __construct(protected OutputInterface $output) {}

    /**
     * The root role is loaded lazily so the session can be created
     * before the database is touched.
     */
    public function current(): ?Role
    {
        if (! $this->loaded) {
            $this->current = Role::query()->whereNull('parent_id')->first();
            $this->loaded = true;
        }

        return $this->current;
    }

    /**
     * Slash separated path of the current role, starting from the root.
     */
    public function path(): string
    {
        $role = $this->current();

        if ($role === null) {
            return '/';
        }

        $segments = [];
        $visited = [];

        while ($role !== null && ! in_array($role->id, $visited, true)) {
            $visited[] = $role->id;
            array_unshift($segments, $role->name);
            $role = $role->parent_id !== null ? Role::find($role->parent_id) : null;
        }

        return implode('/', $segments);
    }

    /**
     * The prompt displayed before every command line.
     */
    public function prompt(): string
    {
        return sprintf('simpleHRBAC [%s] > ', $this->path());
    }

    public function completer(): ShellCompleter
    {
        return new ShellCompleter($this);
    }

    /**
     * Execute a single shell line.
     *
     * Returns false when the user asked to leave the shell (exit/quit).
     */
    public function execute(string $line): bool
    {
        $tokens = $this->tokenize($line);

        if ($tokens === []) {
            return true;
        }

        $command = strtolower(array_shift($tokens));

        try {
            switch ($command) {
                case 'ls':
                    $this->cmdLs();
                    break;

                case 'cd':
                    $this->cmdCd($tokens);
                    break;

                case 'add':
                    $this->cmdAdd($tokens);
                    break;

                case 'rm':
                    $this->cmdRm($tokens);
                    break;

                case 'mv':
                    $this->cmdMv($tokens);
                    break;

                case 'help':
                case '?':
                    $this->cmdHelp();
                    break;

                case 'exit':
                case 'quit':
                    return false;

                default:
                    $this->fail(sprintf(
                        'Unknown command "%s". Type "help" to list the available commands.',
                        $this->e($command)
                    ));
            }
        } catch (RBACException $exception) {
            $this->fail($exception->getMessage());
        } catch (QueryException $exception) {
            $this->fail('Database error: '.$exception->getMessage());
        }

        return true;
    }

    /**
     * Split a line into tokens, honouring simple double/single quoted arguments.
     *
     * @return list<string>
     */
    protected function tokenize(string $line): array
    {
        return array_column($this->tokenizeWithOffsets($line), 'value');
    }

    /**
     * Split a line into tokens and keep their byte offsets.
     *
     * `start` is the offset of the token in the line, `contentStart` the
     * offset where its value begins — one byte later for a token that
     * starts with a quote, because readline treats the quote as a word
     * break character and completes only what follows it.
     *
     * @return list<array{value: string, start: int, contentStart: int}>
     */
    public function tokenizeWithOffsets(string $line): array
    {
        preg_match_all('/"[^"]*"|\'[^\']*\'|\S+/', $line, $matches, PREG_OFFSET_CAPTURE);

        $tokens = [];

        foreach ($matches[0] as [$token, $start]) {
            $length = strlen($token);
            $quoted = $length >= 2 && ($token[0] === '"' || $token[0] === "'") && $token[$length - 1] === $token[0];

            $tokens[] = [
                'value' => $quoted ? substr($token, 1, -1) : $token,
                'start' => $start,
                'contentStart' => $start + (($token[0] === '"' || $token[0] === "'") ? 1 : 0),
            ];
        }

        return $tokens;
    }

    /**
     * Length of the shortest run of tokens that resolves to an existing role.
     *
     * Role names may contain spaces, so a reference written as several
     * tokens belongs together until it resolves; returns 0 when no run
     * of the tokens resolves to a role.
     *
     * @param  list<string>  $tokens
     */
    public function roleReferenceLength(array $tokens): int
    {
        if ($tokens === []) {
            return 0;
        }

        foreach (range(1, count($tokens)) as $length) {
            $reference = implode(' ', array_slice($tokens, 0, $length));

            if ($reference !== '' && $this->resolveRole($reference) !== null) {
                return $length;
            }
        }

        return 0;
    }

    /**
     * List the children of the current role.
     */
    protected function cmdLs(): void
    {
        $role = $this->current();

        if ($role === null) {
            $this->fail('There are no roles yet. Use "add role" to create the root role.');

            return;
        }

        $children = $role->immediateChildren();

        $this->info(sprintf(
            'Role #%d %s%s — %d child role(s), %d user(s), %d permission(s)',
            $role->id,
            $this->e($role->name),
            $role->description !== null && $role->description !== '' ? ' ('.$this->e($role->description).')' : '',
            $children->count(),
            $role->users()->count(),
            $role->permissions()->count()
        ));
        $this->line();

        $this->info('Child roles:');
        $this->line();
        if ($children->isEmpty()) {
            $this->warn('No child roles here. Use "add role" to create one under the current role.');

            return;
        }

        $rows = [];

        foreach ($children as $child) {
            $rows[] = [
                '#'.$child->id,
                $this->e($child->name),
                $child->description !== null && $child->description !== '' ? $this->e($child->description) : '—',
            ];
        }

        $this->renderTable(
            ['ID', 'Name', 'Description'],// 'Users (id: name)', 'Permissions (id: name — description)'],
            $rows
        );

        $this->line();
        $this->info('Users with this role:');
        $users = $role->users;
        $this->line();
        $this->renderTable(
            ['ID', 'Name <email>'],
            $users->map(fn (Model $user): array => [
                '#'.$user->id,
                $this->e($this->userLabel($user, true)),
            ])->toArray()
        );

        $this->line();
        $this->info('Permissions attached to this role:');
        $permissions = $role->permissions;
        $this->line();
        $this->renderTable(
            ['ID', 'Name', 'Description'],
            $permissions->map(fn (Permission $permission): array => [
                '#'.$permission->id,
                $this->e($permission->name),
                $permission->description !== null && $permission->description !== '' ? $this->e($permission->description) : '—',
            ])->toArray()
        );
    }

    /**
     * Change the current role: `cd <role>`, `cd ..` or `cd /`.
     *
     * Role names may contain spaces, so every argument is part of the
     * target: `cd Υπάλληλος (ΑΦ)` works without quoting (a quoted
     * target still works, the quotes are stripped by the tokenizer).
     *
     * @param  list<string>  $args
     */
    protected function cmdCd(array $args): void
    {
        if ($args === []) {
            $this->fail('Usage: cd <role>');

            return;
        }

        $target = implode(' ', $args);

        if ($target === '/') {
            $root = Role::query()->whereNull('parent_id')->first();

            if ($root === null) {
                $this->fail('There are no roles yet. Use "add role" to create the root role.');

                return;
            }

            $this->current = $root;
            $this->loaded = true;

            return;
        }

        if ($target === '..') {
            $role = $this->current();

            if ($role === null) {
                $this->fail('There are no roles yet. Use "add role" to create the root role.');

                return;
            }

            if ($role->parent_id === null) {
                $this->warn('Already at the root role.');

                return;
            }

            $this->current = Role::find($role->parent_id);
            $this->loaded = true;

            return;
        }

        $role = $this->resolveRoleOrFail($target);

        if ($role === null) {
            return;
        }

        $this->current = $role;
        $this->loaded = true;
    }

    /**
     * Dispatch `add role`, `add permission [--new]` and `add user`.
     *
     * @param  list<string>  $args
     */
    protected function cmdAdd(array $args): void
    {
        $type = strtolower($args[0] ?? '');
        $rest = array_slice($args, 1);

        match ($type) {
            'role' => $this->addRole(),
            'permission' => $this->addPermission(in_array('--new', $rest, true)),
            'user' => $this->addUser(),
            default => $this->fail('Usage: add <role|permission [--new]|user>'),
        };
    }

    protected function addRole(): void
    {
        $name = text(
            'Role name',
            'e.g. Managers',
            '',
            true,
            fn (string $value): ?string => Role::query()->where('name', $value)->exists()
                ? 'A role named "'.$value.'" already exists.'
                : null,
            'Must be unique.'
        );

        $description = text(
            'Description (optional)',
            'e.g. Manages the sales team',
            '',
            false,
            null,
            'Press enter to skip.'
        );

        $parent = $this->current();

        try {
            $role = Role::create([
                'name' => $name,
                'description' => $description === '' ? null : $description,
                'parent_id' => $parent?->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->fail('A role with that name already exists.');

            return;
        }

        if ($parent === null) {
            $this->current = $role;
            $this->loaded = true;
        }

        $this->info(sprintf('Role #%d "%s" created.', $role->id, $this->e($role->name)));
    }

    protected function addPermission(bool $new): void
    {
        $role = $this->current();

        if ($role === null) {
            $this->fail('There are no roles yet. Use "add role" to create the root role.');

            return;
        }

        if ($new) {
            $name = text(
                'Permission name',
                'e.g. posts.edit',
                '',
                true,
                fn (string $value): ?string => Permission::query()->where('name', $value)->exists()
                    ? 'A permission named "'.$value.'" already exists.'
                    : null,
                'Must be unique.'
            );

            $description = text(
                'Description (optional)',
                'e.g. Edit blog posts',
                '',
                false,
                null,
                'Press enter to skip.'
            );

            try {
                $permission = Permission::create([
                    'name' => $name,
                    'description' => $description === '' ? null : $description,
                ]);
            } catch (UniqueConstraintViolationException) {
                $this->fail('A permission with that name already exists.');

                return;
            }

            $role->permissions()->attach($permission->id);
            $this->invalidateRoleUserCaches($role);

            $this->info(sprintf(
                'Permission #%d "%s" created and attached to role #%d "%s".',
                $permission->id,
                $this->e($permission->name),
                $role->id,
                $this->e($role->name)
            ));

            return;
        }

        $attachedIds = $role->permissions()->pluck('permissions.id');
        $available = Permission::query()
            ->whereNotIn('id', $attachedIds)
            ->orderBy('name')
            ->get();

        if ($available->isEmpty()) {
            $this->warn(Permission::query()->exists()
                ? 'Every permission is already attached to this role.'
                : 'There are no permissions yet. Use "add permission --new" to create one.');

            return;
        }

        $options = [];

        foreach ($available as $permission) {
            $options[$permission->id] = $permission->id.': '.$permission->name
                .($permission->description !== null && $permission->description !== '' ? ' — '.$permission->description : '');
        }

        $selected = multiselect(
            'Select the permissions to attach to "'.$role->name.'"',
            $options,
            [],
            5,
            false,
            null,
            'Use the space bar to select, enter to confirm.'
        );

        if ($selected === []) {
            $this->warn('No permissions selected.');

            return;
        }

        $role->permissions()->attach($selected);
        $this->invalidateRoleUserCaches($role);

        $this->info(sprintf(
            'Attached %d permission(s) to role #%d "%s".',
            count($selected),
            $role->id,
            $this->e($role->name)
        ));
    }

    protected function addUser(): void
    {
        $role = $this->current();

        if ($role === null) {
            $this->fail('There are no roles yet. Use "add role" to create the root role.');

            return;
        }

        $userClass = DataHelper::getUserModelClass();
        $users = $userClass::query()->orderBy('id')->get();

        if ($users->isEmpty()) {
            $this->warn('There are no users to choose from.');

            return;
        }

        $alreadyAssigned = $role->users()->get()->pluck('id');
        $available = $users->reject(fn (Model $user): bool => $alreadyAssigned->contains($user->id));

        if ($available->isEmpty()) {
            $this->warn('All users already have this role.');

            return;
        }

        $options = [];

        foreach ($available as $user) {
            $options[$user->id] = $user->id.': '.$this->userLabel($user, true);
        }

        $selected = multiselect(
            'Select the users to grant "'.$role->name.'"',
            $options,
            [],
            5,
            false,
            null,
            'Use the space bar to select, enter to confirm.'
        );

        if ($selected === []) {
            $this->warn('No users selected.');

            return;
        }

        $role->users()->attach($selected);

        $this->info(sprintf(
            'Role #%d "%s" granted to %d user(s).',
            $role->id,
            $this->e($role->name),
            count($selected)
        ));
    }

    /**
     * Dispatch `rm role|user|permission <reference>`.
     *
     * Names may contain spaces, so every argument after the type is
     * part of the reference.
     *
     * @param  list<string>  $args
     */
    protected function cmdRm(array $args): void
    {
        $type = strtolower($args[0] ?? '');
        $reference = count($args) > 1 ? implode(' ', array_slice($args, 1)) : null;

        if (! in_array($type, ['role', 'user', 'permission'], true) || $reference === null) {
            $this->fail('Usage: rm <role|user|permission> <reference>');

            return;
        }

        match ($type) {
            'role' => $this->rmRole($reference),
            'user' => $this->rmUser($reference),
            'permission' => $this->rmPermission($reference),
        };
    }

    protected function rmRole(string $reference): void
    {
        $role = $this->resolveRoleOrFail($reference);

        if ($role === null) {
            return;
        }

        if ($role->parent_id === null) {
            $this->fail('The root role cannot be deleted.');

            return;
        }

        $children = $role->children();

        if ($children->isNotEmpty()) {
            $this->fail(sprintf(
                'Role #%d "%s" cannot be deleted: it still has %d child role(s).',
                $role->id,
                $this->e($role->name),
                $children->count()
            ));

            return;
        }

        $userCount = $role->users()->count();

        if ($userCount > 0) {
            $this->fail(sprintf(
                'Role #%d "%s" cannot be deleted: it is still assigned to %d user(s). Remove them first with "rm user".',
                $role->id,
                $this->e($role->name),
                $userCount
            ));

            return;
        }

        $id = $role->id;
        $parentId = $role->parent_id;
        $name = $role->name;

        $role->delete();

        if ($this->current()?->id === $id) {
            $this->current = $parentId !== null ? Role::find($parentId) : null;
            $this->loaded = true;
        }

        $this->info(sprintf('Role #%d "%s" deleted.', $id, $this->e($name)));
    }

    protected function rmUser(string $reference): void
    {
        $role = $this->current();

        if ($role === null) {
            $this->fail('There are no roles yet. Use "add role" to create the root role.');

            return;
        }

        $user = $this->resolveUserOrFail($reference);

        if ($user === null) {
            return;
        }

        if (! $role->users()->whereKey($user->id)->exists()) {
            $this->warn(sprintf(
                'User #%d %s does not have role #%d "%s".',
                $user->id,
                $this->e($this->userLabel($user)),
                $role->id,
                $this->e($role->name)
            ));

            return;
        }

        $role->users()->detach($user->id);

        $this->info(sprintf(
            'User #%d %s no longer has role #%d "%s".',
            $user->id,
            $this->e($this->userLabel($user)),
            $role->id,
            $this->e($role->name)
        ));
    }

    protected function rmPermission(string $reference): void
    {
        $role = $this->current();

        if ($role === null) {
            $this->fail('There are no roles yet. Use "add role" to create the root role.');

            return;
        }

        $permission = $this->resolvePermissionOrFail($reference);

        if ($permission === null) {
            return;
        }

        if (! $role->permissions()->whereKey($permission->id)->exists()) {
            $this->warn(sprintf(
                'Permission #%d "%s" is not attached to role #%d "%s".',
                $permission->id,
                $this->e($permission->name),
                $role->id,
                $this->e($role->name)
            ));

            return;
        }

        $role->permissions()->detach($permission->id);
        $this->invalidateRoleUserCaches($role);

        $this->info(sprintf(
            'Permission #%d "%s" detached from role #%d "%s".',
            $permission->id,
            $this->e($permission->name),
            $role->id,
            $this->e($role->name)
        ));
    }

    /**
     * Move a role under a new parent: `mv <role> <path>`.
     *
     * The target accepts an id, a name or a slash path from the root.
     * Both arguments may contain spaces, so the reference is the
     * shortest run of arguments that resolves to an existing role and
     * everything after it belongs to the target.
     *
     * @param  list<string>  $args
     */
    protected function cmdMv(array $args): void
    {
        if (count($args) < 2) {
            $this->fail('Usage: mv <role> <path>');

            return;
        }

        [$reference, $target] = $this->splitMvArguments($args);

        if ($reference === '' || $target === '') {
            $this->fail('Usage: mv <role> <path>');

            return;
        }

        $role = $this->resolveRoleOrFail($reference);

        if ($role === null) {
            return;
        }

        if ($role->parent_id === null) {
            $this->fail('The root role cannot be moved.');

            return;
        }

        $parent = $this->resolvePath($target);

        if ($parent === null) {
            return;
        }

        if ($parent->id === $role->id) {
            $this->fail('A role cannot be moved under itself.');

            return;
        }

        $isDescendant = DB::table('role_tree')
            ->where('parent', $role->id)
            ->where('child', $parent->id)
            ->exists();

        if ($isDescendant) {
            $this->fail('Cannot move a role under one of its own descendants.');

            return;
        }

        if ((int) $role->parent_id === $parent->id) {
            $this->warn(sprintf(
                'Role #%d "%s" is already under #%d "%s".',
                $role->id,
                $this->e($role->name),
                $parent->id,
                $this->e($parent->name)
            ));

            return;
        }

        $role->parent_id = $parent->id;
        $role->save();

        $this->info(sprintf(
            'Moved role #%d "%s" under #%d "%s".',
            $role->id,
            $this->e($role->name),
            $parent->id,
            $this->e($parent->name)
        ));
    }

    /**
     * Split `mv` arguments into the role reference and the target.
     *
     * The reference is the shortest run of arguments that resolves to
     * an existing role — role names may contain spaces — and whatever
     * follows it belongs to the target. When no run resolves, the first
     * argument is used as the reference so the error names what was
     * typed. The target may be empty; the caller reports the usage.
     *
     * @param  list<string>  $args
     * @return array{string, string}
     */
    protected function splitMvArguments(array $args): array
    {
        $used = $this->roleReferenceLength($args);

        if ($used === 0) {
            $used = 1;
        }

        return [
            implode(' ', array_slice($args, 0, $used)),
            implode(' ', array_slice($args, $used)),
        ];
    }

    protected function cmdHelp(): void
    {
        $this->renderTable(['Command', 'Usage', 'Description'], [
            ['ls', 'ls', 'List the child roles of the current role with their users and permissions'],
            ['cd', 'cd <role>', 'Change the current role by id or name; ".." goes up, "/" goes to the root'],
            ['add role', 'add role', 'Create a new role under the current one (unique name, optional description)'],
            ['add permission', 'add permission [--new]', 'Attach existing permissions, or create and attach a new one with --new'],
            ['add user', 'add user', 'Grant the current role to the selected users'],
            ['mv', 'mv <role> <path>', 'Move a role under a new parent given by id, name or path from the root'],
            ['rm role', 'rm role <role>', 'Delete a role that has no children and no users'],
            ['rm user', 'rm user <user>', 'Remove the current role from the given user'],
            ['rm permission', 'rm permission <permission>', 'Detach the given permission from the current role'],
            ['help', 'help', 'Show this help'],
            ['exit', 'exit | quit', 'Leave the shell (Ctrl+D also works)'],
        ]);

        $this->line('Arguments written as <role>, <user> or <permission> accept an id or a name. Press <comment>TAB</comment> to complete.');
    }

    /**
     * Resolve a role from an id or a name.
     */
    protected function resolveRole(string $reference): ?Role
    {
        if (ctype_digit($reference)) {
            $role = Role::find((int) $reference);

            if ($role !== null) {
                return $role;
            }
        }

        return Role::query()->where('name', $reference)->first();
    }

    protected function resolveRoleOrFail(string $reference): ?Role
    {
        $role = $this->resolveRole($reference);

        if ($role === null) {
            $this->fail(sprintf('Role "%s" was not found.', $this->e($reference)));
        }

        return $role;
    }

    /**
     * Resolve a target path: either a plain role (id/name) or a slash
     * separated path starting from the root role.
     */
    protected function resolvePath(string $reference): ?Role
    {
        if (! str_contains($reference, '/')) {
            return $this->resolveRoleOrFail($reference);
        }

        $root = Role::query()->whereNull('parent_id')->first();

        if ($root === null) {
            $this->fail('There are no roles yet. Use "add role" to create the root role.');

            return null;
        }

        $segments = array_values(array_filter(explode('/', $reference), fn (string $segment): bool => $segment !== ''));

        if (! str_starts_with($reference, '/')) {
            $first = array_shift($segments);

            if ($first !== $root->name && (string) $root->id !== $first) {
                $this->fail(sprintf(
                    'A path must start from the root role, e.g. "%s/...".',
                    $this->e($root->name)
                ));

                return null;
            }
        }

        if ($segments === []) {
            return $root;
        }

        $node = $root;

        foreach ($segments as $segment) {
            $next = $node->immediateChildren()->first(
                fn (Role $child): bool => $child->name === $segment || (string) $child->id === $segment
            );

            if ($next === null) {
                $this->fail(sprintf(
                    'Role "%s" was not found under "%s".',
                    $this->e($segment),
                    $this->e($node->name)
                ));

                return null;
            }

            $node = $next;
        }

        return $node;
    }

    /**
     * Resolve a user from an id, name or e-mail. Duplicate names are
     * disambiguated with a selection prompt.
     */
    protected function resolveUser(string $reference): ?Model
    {
        $userClass = DataHelper::getUserModelClass();
        $users = $userClass::query()->orderBy('id')->get();

        if (ctype_digit($reference)) {
            $user = $users->first(fn (Model $candidate): bool => (string) $candidate->id === $reference);

            if ($user !== null) {
                return $user;
            }
        }

        $matches = $users->filter(
            fn (Model $candidate): bool => $candidate->getAttribute('name') === $reference
                || $candidate->getAttribute('email') === $reference
        )->keyBy('id');

        if ($matches->count() > 1) {
            $options = [];

            foreach ($matches as $candidate) {
                $options[$candidate->id] = $candidate->id.': '.$this->userLabel($candidate, true);
            }

            $selected = select('Several users match "'.$this->e($reference).'". Choose one:', $options);

            return $matches->get($selected);
        }

        return $matches->first();
    }

    protected function resolveUserOrFail(string $reference): ?Model
    {
        $user = $this->resolveUser($reference);

        if ($user === null) {
            $this->fail(sprintf('User "%s" was not found.', $this->e($reference)));
        }

        return $user;
    }

    protected function resolvePermission(string $reference): ?Permission
    {
        if (ctype_digit($reference)) {
            $permission = Permission::find((int) $reference);

            if ($permission !== null) {
                return $permission;
            }
        }

        return Permission::query()->where('name', $reference)->first();
    }

    protected function resolvePermissionOrFail(string $reference): ?Permission
    {
        $permission = $this->resolvePermission($reference);

        if ($permission === null) {
            $this->fail(sprintf('Permission "%s" was not found.', $this->e($reference)));
        }

        return $permission;
    }

    /**
     * Drop the cached permission answers of every user holding the given
     * role (or any of its descendants), used after permission changes
     * which are not observed automatically.
     */
    protected function invalidateRoleUserCaches(Role $role): void
    {
        if (! app()->bound('rbac.service')) {
            return;
        }

        $service = app('rbac.service');

        $roleIds = DB::table('role_tree')
            ->where('parent', $role->id)
            ->pluck('child')
            ->push($role->id)
            ->unique();

        $userIds = DB::table('role_user')
            ->whereIn('role_id', $roleIds)
            ->pluck('user_id')
            ->unique();

        foreach ($userIds as $userId) {
            $service->invalidateUserCache((int) $userId);
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    protected function renderTable(array $headers, array $rows): void
    {
        (new Table($this->output))
            ->setHeaders($headers)
            ->setRows($rows)
            ->render();
    }

    protected function userLabel(Model $user, bool $withEmail = false): string
    {
        $name = $user->getAttribute(config('simple-hrbac.user_name_column', 'name'));

        if (! is_string($name) || $name === '') {
            $name = $user->getAttribute('email');
        }

        if (! is_string($name) || $name === '') {
            $name = '#'.$user->id;
        }

        if ($withEmail) {
            $email = $user->getAttribute('email');

            if (is_string($email) && $email !== '' && $email !== $name) {
                $name .= ' <'.$email.'>';
            }
        }

        return $name;
    }

    protected function line(string $message = ''): void
    {
        $this->output->writeln($message);
    }

    protected function info(string $message): void
    {
        $this->output->writeln('<info>'.$message.'</info>');
    }

    protected function warn(string $message): void
    {
        $this->output->writeln('<comment>'.$message.'</comment>');
    }

    protected function fail(string $message): void
    {
        $this->output->writeln('<error> '.$message.' </error>');
    }

    /**
     * Escape user supplied text so it is never interpreted as a style tag.
     */
    protected function e(string $value): string
    {
        return OutputFormatter::escape($value);
    }
}
