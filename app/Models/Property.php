<?php

namespace App\Models;

use App\Tenancy\BelongsToCurrentGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use BelongsToCurrentGroup, SoftDeletes;

    protected $fillable = [
        'group_id', 'pms_provider_id', 'name', 'name_ar', 'code', 'pms_property_ref', 'timezone',
        'night_audit_time', 'currency', 'total_rooms', 'allowed_sender_emails', 'aliases', 'is_active',
        'mcp_connection_id', 'mcp_settings',
    ];

    protected function casts(): array
    {
        return [
            'allowed_sender_emails' => 'array',
            'aliases' => 'array',
            'mcp_settings' => 'array',
            'is_active' => 'boolean',
            'total_rooms' => 'integer',
        ];
    }

    public function pmsProvider(): BelongsTo
    {
        return $this->belongsTo(PmsProvider::class);
    }

    public function mcpConnection(): BelongsTo
    {
        return $this->belongsTo(McpConnection::class);
    }

    public function reportDocuments(): HasMany
    {
        return $this->hasMany(ReportDocument::class);
    }

    /** @return list<string> every name this hotel may appear under in a report or subject */
    public function knownNames(): array
    {
        return array_values(array_filter([$this->name, $this->name_ar, ...($this->aliases ?? [])]));
    }

    public function acceptsSender(?string $email): bool
    {
        if ($email === null) {
            return false;
        }

        $allowed = array_map(
            fn (string $e) => mb_strtolower(trim($e)),
            $this->allowed_sender_emails ?? [],
        );

        return in_array(mb_strtolower(trim($email)), $allowed, true);
    }
}
