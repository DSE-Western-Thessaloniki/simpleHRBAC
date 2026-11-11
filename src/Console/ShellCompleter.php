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
     * `$textStart` is the byte offset in `$buffer` where the word being
     * completed begins: readline replaces exactly that slice of the line
     * with every candidate returned here, so each candidate is cut from
     * the same offset. When it is not given (tests, non-readline callers)
     * it is derived from the buffer with readline's word break characters.
     *
     * The argument being typed is located per command instead of by a
     * plain whitespace split, because argument values may contain
     * spaces (`cd Υπάλληλος (ΑΦ)`, `mv Υπάλληλος (ΑΦ) Root/ΠΡΟ`).
     *
     * @return list<string>
     */
    public function complete(string $buffer, ?int $textStart = null): array
    {
        $textStart ??= $this->wordStart($buffer);

        $tokens = $this->session->tokenizeWithOffsets($buffer);
        $endsWithWhitespace = preg_match('/\s$/', $buffer) === 1;

        // The command word itself is being typed: no token at all, or a
        // single token the cursor still sits in.
        if ($tokens === [] || (count($tokens) === 1 && ! $endsWithWhitespace)) {
            $argumentStart = $tokens[0]['contentStart'] ?? $textStart;

            return $this->candidatesFor(
                ShellSession::COMMANDS,
                substr($buffer, $argumentStart),
                $argumentStart,
                $textStart
            );
        }

        $command = strtolower((string) $tokens[0]['value']);
        $tail = $endsWithWhitespace ? null : array_pop($tokens);
        $arguments = array_slice($tokens, 1);

        [$position, $argumentStart] = $this->locateArgument($command, $arguments, $tail, $textStart);

        $candidates = match (true) {
            $command === 'cd' && $position === 1 => $this->roleTargets(),
            $command === 'mv' && $position === 1 => $this->roleTargets(),
            $command === 'mv' && $position === 2 => array_merge($this->roleTargets(), $this->pathTargets()),
            $command === 'rm' && $position === 1 => ['role', 'user', 'permission'],
            $command === 'rm' && $position === 2 => $this->referenceTargets((string) ($tokens[1]['value'] ?? '')),
            $command === 'add' && $position === 1 => ['role', 'permission', 'user'],
            $command === 'add' && $position === 2 && strtolower((string) ($tokens[1]['value'] ?? '')) === 'permission' => ['--new'],
            $command === 'help' && $position === 1 => ShellSession::COMMANDS,
            default => [],
        };

        return $this->candidatesFor($candidates, substr($buffer, $argumentStart), $argumentStart, $textStart);
    }

    /**
     * Position of the argument the cursor types and the byte offset in
     * the buffer where that argument starts.
     *
     * Arguments are grouped per command: `cd` has a single target that
     * may span several tokens, `rm` has a single word type followed by
     * a reference that may span several tokens, and `mv` consumes the
     * tokens of its reference until they resolve to a role. Every other
     * command takes one token per argument. The tail, when given, is
     * the token the cursor sits in and always belongs to the argument
     * being typed.
     *
     * @param  list<array{value: string, start: int, contentStart: int}>  $arguments
     * @param  array{value: string, start: int, contentStart: int}|null  $tail
     * @return array{0: int, 1: int}
     */
    protected function locateArgument(string $command, array $arguments, ?array $tail, int $textStart): array
    {
        switch ($command) {
            case 'cd':
                // One target: it keeps extending while any token remains.
                return [1, $arguments[0]['contentStart'] ?? $tail['contentStart'] ?? $textStart];

            case 'rm':
                // The type is a single word, everything after it is the reference.
                if ($arguments === []) {
                    return [1, $tail['contentStart'] ?? $textStart];
                }

                return [2, $arguments[1]['contentStart'] ?? $tail['contentStart'] ?? $textStart];

            case 'mv':
                // The reference spans tokens until they resolve to a role.
                $used = $this->session->roleReferenceLength(array_column($arguments, 'value'));

                if ($used === 0) {
                    return [1, $arguments[0]['contentStart'] ?? $tail['contentStart'] ?? $textStart];
                }

                return [2, $arguments[$used]['contentStart'] ?? $tail['contentStart'] ?? $textStart];

            default:
                // One token per argument: every remaining token completes
                // one argument, the tail starts the next one.
                return [1 + count($arguments), $tail['contentStart'] ?? $textStart];
        }
    }

    /**
     * Keep the candidates that start with the typed argument and cut them
     * from the offset readline replaces, so inserting the common prefix of
     * the result never rewrites what is already on the line.
     *
     * Role names contain spaces and parentheses, which readline treats as
     * word break characters: once the cursor is past one of them, only the
     * fragment after it is replaced, and returning whole names from that
     * point duplicates the line instead of completing it.
     *
     * @param  list<string>  $candidates
     * @return list<string>
     */
    protected function candidatesFor(array $candidates, string $typing, int $argumentStart, int $textStart): array
    {
        $offset = max(0, $textStart - $argumentStart);

        $matches = [];

        foreach ($candidates as $candidate) {
            $candidate = (string) $candidate;

            // A candidate that ends exactly at the replaced offset would
            // yield an empty string — nothing can be inserted for it.
            if (str_starts_with($candidate, $typing) && strlen($candidate) > $offset) {
                $matches[] = substr($candidate, $offset);
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * Byte offset where readline starts the word being completed: just
     * after the last word break character of the buffer (readline's
     * `rl_basic_word_break_characters`).
     */
    protected function wordStart(string $buffer): int
    {
        $breakCharacters = " \t\n\"\\'`@\$><=;|&{(";

        for ($offset = strlen($buffer) - 1; $offset >= 0; $offset--) {
            if (strpbrk($buffer[$offset], $breakCharacters) !== false) {
                return $offset + 1;
            }
        }

        return 0;
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
