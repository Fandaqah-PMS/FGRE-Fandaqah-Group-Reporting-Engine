<?php

namespace App\Models;

use App\Tenancy\BelongsToCurrentGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A PMS MCP server FGRE can pull reports from. The secret is encrypted and never serialized. */
class McpConnection extends Model
{
    use BelongsToCurrentGroup;

    protected $fillable = [
        'group_id', 'pms_provider_id', 'name', 'url', 'auth_type', 'auth_header', 'secret',
        'is_active', 'last_polled_at', 'last_error',
    ];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_polled_at' => 'datetime',
        ];
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /** @return array<string, string> */
    public function authHeaders(): array
    {
        return match ($this->auth_type) {
            'bearer' => $this->secret ? ['Authorization' => 'Bearer '.$this->secret] : [],
            'header' => $this->secret && $this->auth_header ? [$this->auth_header => $this->secret] : [],
            default => [],
        };
    }
}
