<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A group's own permission set for one role. Not group-scoped on purpose: it is read while
 * checking permissions (before any group scope applies), always filtered by group_id.
 */
class GroupRole extends Model
{
    protected $fillable = ['group_id', 'role', 'permissions'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }
}
