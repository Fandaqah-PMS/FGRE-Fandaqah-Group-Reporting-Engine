<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\SetPasswordNotification;
use App\Support\RolePermissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

#[Fillable(['name', 'email', 'password', 'locale', 'theme'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(HotelGroup::class, 'group_user', 'user_id', 'group_id')
            ->withPivot(['role', 'all_properties'])
            ->withTimestamps();
    }

    public function currentGroup(): BelongsTo
    {
        return $this->belongsTo(HotelGroup::class, 'current_group_id');
    }

    /** Properties this user was limited to (only used when all_properties = false). */
    public function restrictedProperties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_user')->withTimestamps();
    }

    public function membership(?int $groupId = null): ?HotelGroup
    {
        $groupId ??= $this->current_group_id;

        return $groupId === null ? null : $this->groups->firstWhere('id', $groupId);
    }

    public function roleIn(?int $groupId = null): ?string
    {
        return $this->membership($groupId)?->pivot->role;
    }

    /** Permission check inside one group (the current group by default). */
    public function hasGroupPermission(string $permission, ?int $groupId = null): bool
    {
        $role = $this->roleIn($groupId);
        if ($role === null) {
            return false;
        }

        return in_array($permission, app(RolePermissions::class)->for($groupId ?? $this->current_group_id, $role), true);
    }

    /**
     * Ids of the properties this user may see in a group.
     *
     * @return Collection<int, int>
     */
    public function accessiblePropertyIds(?int $groupId = null): Collection
    {
        $membership = $this->membership($groupId);
        if ($membership === null) {
            return collect();
        }

        $query = Property::forGroup($membership->id);

        if (! $membership->pivot->all_properties) {
            $query->whereIn('id', $this->restrictedProperties()->select('properties.id'));
        }

        return $query->pluck('id');
    }

    /** Password links (forgotten password, or the invitation of a new user) in the user's language. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new SetPasswordNotification($token));
    }

    public function switchGroup(HotelGroup $group): void
    {
        if ($this->membership($group->id) === null) {
            throw new InvalidArgumentException('User is not a member of this group.');
        }

        $this->forceFill(['current_group_id' => $group->id])->save();
    }
}
