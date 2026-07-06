<?php

namespace Dsewth\SimpleHRBAC\Helpers;

use Dsewth\SimpleHRBAC\Models\Permission;
use Illuminate\Support\Collection;

class PermissionWildcard
{
    /**
     * The wildcard character recognised inside permission names.
     */
    public const WILDCARD = '*';

    /**
     * Does the given permission name contain a wildcard?
     */
    public static function isPattern(string $name): bool
    {
        return str_contains($name, self::WILDCARD);
    }

    /**
     * Convert a permission name (which may contain `*`) into an anchored
     * PCRE regex. Every `*` is greedy (matches across dots); every other
     * character is treated literally.
     */
    public static function toRegex(string $pattern): string
    {
        $parts = explode(self::WILDCARD, $pattern);
        $quoted = array_map(fn ($part) => preg_quote($part, '/'), $parts);

        return '/^'.implode('.*', $quoted).'$/s';
    }

    /**
     * Does $pattern match $candidate?
     *
     * If $pattern contains no wildcard, this is a literal string comparison.
     */
    public static function matches(string $pattern, string $candidate): bool
    {
        if (! self::isPattern($pattern)) {
            return $pattern === $candidate;
        }

        return (bool) preg_match(self::toRegex($pattern), $candidate);
    }

    /**
     * Does $broad strictly cover $specific?
     *
     * Returns true when $broad is a wildcard pattern that matches the literal
     * name $specific, and the two strings are not identical. Equal strings are
     * never considered covering, so simplification keeps exactly one of any
     * duplicate names.
     */
    public static function covers(string $broad, string $specific): bool
    {
        if ($broad === $specific) {
            return false;
        }

        if (! self::isPattern($broad)) {
            return false;
        }

        return self::matches($broad, $specific);
    }

    /**
     * Remove every permission from $permissions whose name is covered by
     * another permission's name in the same collection.
     *
     * Given `view.*`, `view.1`, `view.2`, the result contains only `view.*`.
     * Two unrelated wildcards (e.g. `view.*` and `edit.*`) both survive.
     *
     * @param  Collection<int, Permission>  $permissions
     * @return Collection<int, Permission>
     */
    public static function simplify(Collection $permissions): Collection
    {
        $names = $permissions->pluck('name')->all();

        return $permissions
            ->reject(function ($permission) use ($names) {
                foreach ($names as $other) {
                    if (self::covers($other, $permission->name)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * Return every permission from $allPermissions that is covered by any
     * permission in $simplifiedPermissions.
     *
     * This is effectively the inverse of simplify for a given known universe
     * of permissions.
     *
     * @param  Collection<int, Permission>  $simplifiedPermissions
     * @param  Collection<int, Permission>|null  $allPermissions
     * @return Collection<int, Permission>
     */
    public static function expand(Collection $simplifiedPermissions, ?Collection $allPermissions = null): Collection
    {
        $names = $simplifiedPermissions->pluck('name')->all();
        $allPermissions = $allPermissions ?? Permission::all();

        return $allPermissions
            ->filter(function ($permission) use ($names) {
                foreach ($names as $name) {
                    if (self::matches($name, $permission->name)) {
                        return true;
                    }
                }

                return false;
            })
            ->reject(fn ($permission) => self::isPattern($permission->name))
            ->unique(fn ($permission) => $permission->id ?? $permission->name)
            ->values();
    }
}
