<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmsProvider extends Model
{
    protected $fillable = ['code', 'name', 'supports_mcp'];

    protected function casts(): array
    {
        return ['supports_mcp' => 'boolean'];
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}
