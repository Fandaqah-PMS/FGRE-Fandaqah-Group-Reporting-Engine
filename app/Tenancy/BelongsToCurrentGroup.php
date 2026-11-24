<?php

namespace App\Tenancy;

use App\Models\HotelGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GroupScope plus a group_id default on create.
 *
 * The group always comes from the authenticated user, never from request
 * input. Hooks use the boot{Trait} convention so models keep their own boot().
 */
trait BelongsToCurrentGroup
{
    public static function bootBelongsToCurrentGroup(): void
    {
        static::addGlobalScope(new GroupScope);

        static::creating(function ($model) {
            if (empty($model->group_id) && auth()->check()) {
                $model->group_id = auth()->user()->current_group_id;
            }
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(HotelGroup::class, 'group_id');
    }

    /** Explicit-group query for jobs and commands (no logged-in user). */
    public function scopeForGroup(Builder $query, int $groupId): Builder
    {
        return $query->withoutGlobalScope(GroupScope::class)->where($this->qualifyColumn('group_id'), $groupId);
    }
}
