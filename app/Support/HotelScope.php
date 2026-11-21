<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Which hotels the pages show: all hotels the user may see in the current group
 * (consolidated), or one of them. Stored in the session per group, so switching
 * group starts from "all hotels" again.
 */
final class HotelScope
{
    public static function key(Request $request): string
    {
        return 'scope.hotel.'.$request->user()->current_group_id;
    }

    /** The selected hotel id, or null for all hotels. Ignores a hotel the user may no longer see. */
    public static function selected(Request $request): ?int
    {
        $id = $request->session()->get(self::key($request));

        return $id !== null && $request->user()->accessiblePropertyIds()->contains((int) $id) ? (int) $id : null;
    }

    /** @return Collection<int, int> */
    public static function propertyIds(Request $request): Collection
    {
        $selected = self::selected($request);

        return $selected === null ? $request->user()->accessiblePropertyIds() : collect([$selected]);
    }
}
