<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'pms_provider_id', 'name', 'code', 'pms_property_ref', 'timezone',
        'currency', 'total_rooms', 'allowed_sender_emails', 'aliases', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allowed_sender_emails' => 'array',
            'aliases' => 'array',
            'is_active' => 'boolean',
            'total_rooms' => 'integer',
        ];
    }

    public function pmsProvider(): BelongsTo
    {
        return $this->belongsTo(PmsProvider::class);
    }

    public function reportDocuments(): HasMany
    {
        return $this->hasMany(ReportDocument::class);
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
