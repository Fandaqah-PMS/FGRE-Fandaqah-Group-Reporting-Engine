<?php

namespace App\Models;

use App\Tenancy\BelongsToCurrentGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Who changed what in the group's settings (users, roles, hotels, group). Append only. */
class AuditLog extends Model
{
    use BelongsToCurrentGroup;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param array<string, mixed> $details */
    public static function record(string $action, ?string $subject = null, array $details = []): void
    {
        $user = auth()->user();

        static::forGroup($user->current_group_id)->create([
            'group_id' => $user->current_group_id,
            'user_id' => $user->id,
            'action' => $action,
            'subject' => $subject,
            'details' => $details ?: null,
            'ip' => request()->ip(),
        ]);
    }
}
