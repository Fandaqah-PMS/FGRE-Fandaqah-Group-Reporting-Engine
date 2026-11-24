<?php

namespace App\Support;

use App\Models\GroupRole;

/**
 * What a role may do in a group. Defaults come from config/fgre_permissions.php; a group's
 * owners may change them for finance, operations and viewer (group_roles). The owner role
 * always has every permission, so a group can never lock itself out.
 *
 * Registered as a singleton: one lookup per group and request.
 */
class RolePermissions
{
    public const FIXED_ROLE = 'owner';

    /** @var array<int, array<string, list<string>>> */
    private array $cache = [];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(config('fgre_permissions.permissions'));
    }

    /** @return list<string> */
    public static function roles(): array
    {
        return array_keys(config('fgre_permissions.roles'));
    }

    /** @return list<string> the default permissions of a role */
    public static function defaults(string $role): array
    {
        $granted = config("fgre_permissions.roles.{$role}", []);

        return in_array('*', $granted, true) ? self::all() : array_values(array_intersect(self::all(), $granted));
    }

    /** @return list<string> */
    public function for(int $groupId, string $role): array
    {
        if ($role === self::FIXED_ROLE) {
            return self::all();
        }

        $this->cache[$groupId] ??= GroupRole::where('group_id', $groupId)->pluck('permissions', 'role')->all();

        $custom = $this->cache[$groupId][$role] ?? null;

        return $custom === null ? self::defaults($role) : array_values(array_intersect(self::all(), $custom));
    }

    public function forget(int $groupId): void
    {
        unset($this->cache[$groupId]);
    }
}
