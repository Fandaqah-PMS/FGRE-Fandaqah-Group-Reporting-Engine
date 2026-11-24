<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\HotelGroup;
use App\Models\Property;
use App\Models\ReportSubscription;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use App\Support\RolePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Members of the current group: add (invitation email), change role and hotel access,
 * remove. Rules: only an owner can give or take the owner role, nobody changes their own
 * role or access, and a group always keeps at least one owner.
 */
class UsersController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage users');
        $group = $this->group($request);
        $groupHotels = Property::pluck('id');

        $members = $group->users()->with('restrictedProperties:id')->orderBy('name')->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->pivot->role,
            'all_properties' => (bool) $u->pivot->all_properties,
            'hotels' => $u->restrictedProperties->pluck('id')->intersect($groupHotels)->values(),
            'last_login_at' => $u->last_login_at?->toIso8601String(),
            'joined_at' => $u->pivot->created_at?->toIso8601String(),
            'is_me' => $u->id === $request->user()->id,
        ]);

        return Inertia::render('Settings/Users', [
            'members' => $members,
            'roles' => RolePermissions::roles(),
            'canAssignOwner' => $this->isOwner($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage users');
        $group = $this->group($request);
        $data = $this->validated($request);
        $email = mb_strtolower($data['email']);

        $user = User::where('email', $email)->first();
        if ($user && $group->users()->whereKey($user->id)->exists()) {
            throw ValidationException::withMessages(['email' => __('This person is already a member of the group.')]);
        }

        $invite = $user === null;
        $user ??= User::create([
            'name' => $data['name'],
            'email' => $email,
            'password' => Str::random(48),   // unusable until they set their own from the invitation
            'locale' => $data['locale'],
        ]);

        DB::transaction(function () use ($group, $user, $data) {
            $group->users()->attach($user->id, ['role' => $data['role'], 'all_properties' => $data['all_properties']]);
            $this->syncHotels($user, $data);
            if ($user->current_group_id === null) {
                $user->forceFill(['current_group_id' => $group->id])->save();
            }
        });

        if ($invite) {
            $this->sendInvitation($request, $user, $group);
        }
        AuditLog::record('user.added', $user->email, ['role' => $data['role'], 'hotels' => $data['all_properties'] ? 'all' : count($data['hotels'])]);

        return back()->with('status', $invite
            ? __('Invitation sent to :email.', ['email' => $user->email])
            : __(':email already had an FGRE account and now has access to this group.', ['email' => $user->email]));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage users');
        $group = $this->group($request);
        $current = $this->membershipRole($group, $user);
        $this->denyIf($user->id === $request->user()->id, __('You cannot change your own role or access.'));

        $data = $this->validated($request, update: true);
        if (($current === RolePermissions::FIXED_ROLE || $data['role'] === RolePermissions::FIXED_ROLE) && ! $this->isOwner($request)) {
            $this->denyIf(true, __('Only an owner can give or take the owner role.'));
        }
        if ($current === RolePermissions::FIXED_ROLE && $data['role'] !== RolePermissions::FIXED_ROLE) {
            $this->keepAnOwner($group, $user);
        }

        DB::transaction(function () use ($group, $user, $data) {
            $group->users()->updateExistingPivot($user->id, ['role' => $data['role'], 'all_properties' => $data['all_properties']]);
            $this->syncHotels($user, $data);
        });
        AuditLog::record('user.updated', $user->email, ['role' => [$current, $data['role']], 'hotels' => $data['all_properties'] ? 'all' : count($data['hotels'])]);

        return back()->with('status', __('Access for :name saved.', ['name' => $user->name]));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage users');
        $group = $this->group($request);
        $role = $this->membershipRole($group, $user);
        $this->denyIf($user->id === $request->user()->id, __('You cannot remove yourself.'));
        if ($role === RolePermissions::FIXED_ROLE) {
            $this->denyIf(! $this->isOwner($request), __('Only an owner can give or take the owner role.'));
            $this->keepAnOwner($group, $user);
        }

        DB::transaction(function () use ($group, $user) {
            $group->users()->detach($user->id);
            $user->restrictedProperties()->detach(Property::pluck('id'));
            ReportSubscription::where('user_id', $user->id)->delete();   // this group's only (group scope)
            if ($user->current_group_id === $group->id) {
                $user->forceFill(['current_group_id' => $user->groups()->value('hotel_groups.id')])->save();
            }
        });
        AuditLog::record('user.removed', $user->email, ['role' => $role]);

        return back()->with('status', __(':name no longer has access to this group.', ['name' => $user->name]));
    }

    /** Send the invitation link again (e.g. it expired or went to spam). */
    public function invite(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage users');
        $group = $this->group($request);
        $this->membershipRole($group, $user);

        $this->sendInvitation($request, $user, $group);
        AuditLog::record('user.invited', $user->email);

        return back()->with('status', __('Invitation sent to :email.', ['email' => $user->email]));
    }

    private function sendInvitation(Request $request, User $user, HotelGroup $group): void
    {
        $token = Password::broker('invites')->createToken($user);
        $name = $user->locale === 'en' ? $group->name : ($group->name_ar ?: $group->name);
        $user->notify(new SetPasswordNotification($token, invitedTo: $name, invitedBy: $request->user()->name));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $update = false): array
    {
        $hotelIds = Property::pluck('id')->all();
        $data = $request->validate([
            'name' => [$update ? 'nullable' : 'required', 'string', 'max:120'],
            'email' => [$update ? 'nullable' : 'required', 'email', 'max:190'],
            'role' => ['required', Rule::in(RolePermissions::roles())],
            'all_properties' => ['required', 'boolean'],
            'hotels' => ['array', Rule::requiredIf(fn () => ! $request->boolean('all_properties')), $request->boolean('all_properties') ? 'nullable' : 'min:1'],
            'hotels.*' => ['integer', Rule::in($hotelIds)],
            'locale' => ['nullable', Rule::in(['ar', 'en'])],
        ]);
        $this->denyIf(! $update && $data['role'] === RolePermissions::FIXED_ROLE && ! $this->isOwner($request), __('Only an owner can give or take the owner role.'));

        return [
            ...$data,
            'all_properties' => (bool) $data['all_properties'],
            'hotels' => $data['all_properties'] ? [] : array_values(array_unique(array_map('intval', $data['hotels'] ?? []))),
            'locale' => $data['locale'] ?? $request->user()->locale ?? 'ar',
        ];
    }

    /** Hotel limits apply per group: only this group's hotels are touched. */
    private function syncHotels(User $user, array $data): void
    {
        $user->restrictedProperties()->detach(Property::pluck('id'));
        if (! $data['all_properties']) {
            $user->restrictedProperties()->attach($data['hotels']);
        }
    }

    /** A refused change shows as a message on the form, not an error page. */
    private function denyIf(bool $condition, string $message): void
    {
        if ($condition) {
            throw ValidationException::withMessages(['role' => $message]);
        }
    }

    private function keepAnOwner(HotelGroup $group, User $leaving): void
    {
        $others = $group->users()->wherePivot('role', RolePermissions::FIXED_ROLE)->where('users.id', '!=', $leaving->id)->count();
        if ($others === 0) {
            throw ValidationException::withMessages(['role' => __('A group needs at least one owner.')]);
        }
    }

    private function membershipRole(HotelGroup $group, User $user): string
    {
        $member = $group->users()->whereKey($user->id)->first();
        abort_if($member === null, 404);

        return $member->pivot->role;
    }

    private function isOwner(Request $request): bool
    {
        return $request->user()->roleIn() === RolePermissions::FIXED_ROLE;
    }

    private function group(Request $request): HotelGroup
    {
        return $request->user()->currentGroup;
    }
}
