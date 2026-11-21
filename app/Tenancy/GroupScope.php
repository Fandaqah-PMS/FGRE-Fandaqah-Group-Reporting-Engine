<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits queries to the logged-in user's current hotel group.
 *
 * With nobody logged in (queue jobs, scheduler, console) no filter is added;
 * background code always passes the group explicitly. Code that must look
 * across groups (document matching, platform admin screens) calls
 * withoutGlobalScope(GroupScope::class) on purpose and says why.
 */
class GroupScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        // A logged-in user without a current group sees nothing, never everything.
        $builder->where($model->qualifyColumn('group_id'), $user->current_group_id ?? 0);
    }
}
