<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** The FGRE customer: a hotel chain that owns several properties. */
class HotelGroup extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'name_ar', 'slug', 'currency', 'timezone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'group_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_user', 'group_id', 'user_id')
            ->withPivot(['role', 'all_properties'])
            ->withTimestamps();
    }
}
