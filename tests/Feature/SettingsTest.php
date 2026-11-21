<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\GroupRole;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesGroups;
use Tests\TestCase;

/** Settings: users, roles & permissions, hotels, profile, password links. */
class SettingsTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withoutVite();
    }

    public function test_owner_invites_a_new_user_limited_to_one_hotel(): void
    {
        $group = $this->group();
        $riyadh = $this->property($group);
        $this->property($group, ['code' => 'JED', 'name' => 'Fandaqah Jeddah']);
        $owner = $this->member($group);

        $this->actingAs($owner)->post('/settings/users', [
            'name' => 'Sara Finance', 'email' => 'Sara@Example.com', 'role' => 'finance', 'all_properties' => false, 'hotels' => [$riyadh->id], 'locale' => 'en',
        ])->assertSessionHas('status');

        $sara = User::where('email', 'sara@example.com')->sole();
        $this->assertSame('finance', $sara->roleIn($group->id));
        $this->assertEquals([$riyadh->id], $sara->accessiblePropertyIds($group->id)->all());
        Notification::assertSentTo($sara, SetPasswordNotification::class, fn ($n) => $n->invitedTo !== null);
        $this->assertSame('user.added', AuditLog::forGroup($group->id)->sole()->action);
    }

    public function test_an_existing_account_is_added_without_a_new_user_or_invitation(): void
    {
        $other = $this->group('Other Group');
        $known = $this->member($other, 'viewer');
        $group = $this->group();
        $this->property($group);

        $this->actingAs($this->member($group))->post('/settings/users', [
            'name' => 'Ignored', 'email' => $known->email, 'role' => 'viewer', 'all_properties' => true,
        ])->assertSessionHas('status');

        $this->assertSame('viewer', $known->fresh()->roleIn($group->id));
        $this->assertNotSame('Ignored', $known->fresh()->name);
        Notification::assertNothingSent();
    }

    public function test_owner_rules_protect_the_group(): void
    {
        $group = $this->group();
        $this->property($group);
        $owner = $this->member($group);
        $admin = $this->member($group, 'finance');
        GroupRole::create(['group_id' => $group->id, 'role' => 'finance', 'permissions' => ['view dashboard', 'manage users']]);

        // Nobody changes their own role; the last owner cannot be demoted.
        $this->actingAs($owner)->put("/settings/users/{$owner->id}", ['role' => 'viewer', 'all_properties' => true])->assertSessionHasErrors('role');
        // A non-owner admin cannot hand out the owner role, nor touch an owner.
        $viewer = $this->member($group, 'viewer');
        $this->actingAs($admin)->put("/settings/users/{$viewer->id}", ['role' => 'owner', 'all_properties' => true])->assertSessionHasErrors('role');
        $this->actingAs($admin)->delete("/settings/users/{$owner->id}")->assertSessionHasErrors('role');
        $this->assertSame('owner', $owner->fresh()->roleIn($group->id));

        // With a second owner, the first can be changed.
        $second = $this->member($group);
        $this->actingAs($second)->put("/settings/users/{$owner->id}", ['role' => 'finance', 'all_properties' => true])->assertSessionHasNoErrors();
        $this->assertSame('finance', $owner->fresh()->roleIn($group->id));
    }

    public function test_owner_changes_role_permissions_for_the_group_only(): void
    {
        $group = $this->group();
        $this->property($group);
        $owner = $this->member($group);
        $finance = $this->member($group, 'finance');
        $otherGroup = $this->group('Other Group');
        $this->property($otherGroup, ['code' => 'X1']);
        $financeElsewhere = $this->member($otherGroup, 'finance');

        $this->assertTrue(Gate::forUser($finance)->allows('export reports'));

        $this->actingAs($owner)->put('/settings/roles/finance', ['permissions' => ['view dashboard', 'view reports', 'view financials']])->assertSessionHas('status');
        $this->app->forgetInstance(RolePermissions::class);

        $this->assertFalse(Gate::forUser($finance->fresh())->allows('export reports'));
        $this->assertTrue(Gate::forUser($financeElsewhere->fresh())->allows('export reports'), 'other groups keep the defaults');

        // Only owners edit roles; the owner role itself is fixed.
        $this->actingAs($finance)->put('/settings/roles/viewer', ['permissions' => []])->assertForbidden();
        $this->actingAs($owner)->put('/settings/roles/owner', ['permissions' => []])->assertNotFound();

        $this->actingAs($owner)->delete('/settings/roles/finance')->assertSessionHas('status');
        $this->app->forgetInstance(RolePermissions::class);
        $this->assertTrue(Gate::forUser($finance->fresh())->allows('export reports'));
    }

    public function test_settings_pages_follow_permissions(): void
    {
        $group = $this->group();
        $this->property($group);
        $owner = $this->member($group);
        $viewer = $this->member($group, 'viewer');

        foreach (['/settings/users', '/settings/roles', '/settings/hotels', '/settings/group', '/settings/activity', '/settings/profile'] as $url) {
            $this->actingAs($owner)->get($url)->assertOk();
        }
        foreach (['/settings/users', '/settings/roles', '/settings/hotels', '/settings/group', '/settings/activity'] as $url) {
            $this->actingAs($viewer)->get($url)->assertForbidden();
        }
        $this->actingAs($viewer)->get('/settings/profile')->assertOk();
    }

    public function test_hotel_settings_validate_senders_and_log_changes(): void
    {
        $group = $this->group();
        $hotel = $this->property($group);
        $owner = $this->member($group);
        $form = ['name' => 'Fandaqah Riyadh', 'code' => 'ruh01', 'pms_provider_id' => $hotel->pms_provider_id, 'total_rooms' => 140, 'timezone' => 'Asia/Riyadh', 'aliases' => ['Fandaqah Hotel Riyadh']];

        $this->actingAs($owner)->put("/settings/hotels/{$hotel->id}", [...$form, 'allowed_sender_emails' => ['not-an-email']])->assertSessionHasErrors('allowed_sender_emails.0');
        $this->actingAs($owner)->put("/settings/hotels/{$hotel->id}", [...$form, 'allowed_sender_emails' => ['Night@Hotel.example']])->assertSessionHasNoErrors();

        $hotel->refresh();
        $this->assertSame(140, $hotel->total_rooms);
        $this->assertSame('RUH01', $hotel->code);
        $this->assertTrue($hotel->acceptsSender('night@hotel.example'));
        $this->actingAs($owner)->get('/settings/activity')->assertInertia(fn (Assert $page) => $page->where('logs.data.0.action', 'hotel.updated'));
    }

    public function test_profile_password_change_needs_the_current_password(): void
    {
        $group = $this->group();
        $user = $this->member($group, 'viewer');

        $this->actingAs($user)->put('/settings/password', ['current_password' => 'wrong', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put('/settings/password', ['current_password' => 'password', 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
    }

    public function test_forgot_password_link_sets_a_new_password_and_signs_in(): void
    {
        $group = $this->group();
        $user = $this->member($group, 'viewer');

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status');   // same answer
        Notification::assertSentTo($user, SetPasswordNotification::class, fn ($n) => $n->invitedTo === null);

        $token = Password::broker()->createToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Fresh-Pass-2026', 'password_confirmation' => 'Fresh-Pass-2026'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('Fresh-Pass-2026', $user->fresh()->password));

        // An invitation token is not accepted as a reset token (separate broker).
        auth()->logout();
        $invite = Password::broker('invites')->createToken($user);
        $this->post('/reset-password', ['token' => $invite, 'email' => $user->email, 'password' => 'Other-Pass-2026', 'password_confirmation' => 'Other-Pass-2026'])
            ->assertSessionHasErrors('email');
    }
}
