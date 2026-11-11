<?php

namespace Dsewth\SimpleHRBAC\Console;

use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Models\Permission;
use Dsewth\SimpleHRBAC\Models\Role;

/**
 * Provides context aware tab completion candidates for the shell line.
 */
class ShellCompleter
{
    public function __construct(protected ShellSession $session) {}

    /**
     * Return the words that complete the given line buffer.
     *
     * @return list<string>
     */
    public function complete(string $buffer): array
    {
        $leftTrimmed = ltrim($buffer);

        if (preg_match('/^cd\s+(.*)$/is', $leftTrimmed, $matches)) {
            return $this->completeRoleTarget($matches[1]);
        }

        $trimmed = trim($buffer);
        $typing = '';
        $words = [];

        if ($trimmed !== '') {
            $words = preg_split('/\s+/', $trimmed);

            if (! preg_match('/\s$/', $buffer)) {
                $typing = (string) array_pop($words);
            }
        }

        $position = count($words);
        $command = strtolower((string) ($words[0] ?? ''));

        $candidates = match (true) {
            $position === 0 => ShellSession::COMMANDS,
            $command === 'cd' && $position === 1 => $this->roleTargets(),
            $command === 'mv' && $position === 1 => $this->roleTargets(),
            $command === 'mv' && $position === 2 => array_merge($this->roleTargets(), $this->pathTargets()),
            $command === 'rm' && $position === 1 => ['role', 'user', 'permission'],
            $command === 'rm' && $position === 2 => $this->referenceTargets((string) ($words[1] ?? '')),
            $command === 'add' && $position === 1 => ['role', 'permission', 'user'],
            $command === 'add' && $position === 2 && strtolower((string) ($words[1] ?? '')) === 'permission' => ['--new'],
            $command === 'help' && $position === 1 => ShellSession::COMMANDS,
            default => [],
        };

        $matches = [];

        foreach ($candidates as $candidate) {
            $candidate = (string) $candidate;

            if (str_starts_with($candidate, $typing)) {
                $matches[] = $candidate;
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * Complete a role name that may contain spaces.
     *
     * @return list<string>
     */
    protected function completeRoleTarget(string $typing): array
    {
        $candidates = array_filter(
            $this->roleTargets(),
            fn (string $candidate): bool => str_starts_with($candidate, $typing)
        );

        if (preg_match('/\s$/', $typing)) {
            $candidates = array_map(
                fn (string $candidate): string => mb_substr($candidate, mb_strlen($typing)),
                $candidates
            );
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return list<string>
     */
    protected function roleTargets(): array
    {
        $targets = ['..', '/'];

        foreach (Role::query()->orderBy('id')->get() as $role) {
            $targets[] = (string) $role->id;
            $targets[] = $role->name;
        }

        return $targets;
    }

    /**
     * Every role path starting from the root, e.g. "Root/Managers/Sales".
     *
     * @return list<string>
     */
    protected function pathTargets(): array
    {
        $paths = [];

        foreach (Role::query()->whereNull('parent_id')->orderBy('id')->get() as $root) {
            $this->collectPaths($root, '', $paths);
        }

        return $paths;
    }

    /**
     * @param  list<string>  $paths
     */
    protected function collectPaths(Role $role, string $prefix, array &$paths): void
    {
        $path = $prefix === '' ? $role->name : $prefix.'/'.$role->name;
        $paths[] = $path;

        foreach ($role->immediateChildren() as $child) {
            $this->collectPaths($child, $path, $paths);
        }
    }

    /**
     * Ids and names of the entity type referenced after `rm`.
     *
     * @return list<string>
     */
    protected function referenceTargets(string $type): array
    {
        return match (strtolower($type)) {
            'role' => $this->roleTargets(),
            'user' => $this->userTargets(),
            'permission' => $this->permissionTargets(),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    protected function userTargets(): array
    {
        $targets = [];
        $userClass = DataHelper::getUserModelClass();

        foreach ($userClass::query()->orderBy('id')->get() as $user) {
            $targets[] = (string) $user->id;

            $name = $user->getAttribute('name');

            if (is_string($name) && $name !== '') {
                $targets[] = $name;
            }
        }

        return $targets;
    }

    /**
     * @return list<string>
     */
    protected function permissionTargets(): array
    {
        $targets = [];

        foreach (Permission::query()->orderBy('id')->get() as $permission) {
            $targets[] = (string) $permission->id;
            $targets[] = $permission->name;
        }

        return $targets;
    }
}
