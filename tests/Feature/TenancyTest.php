<?php

namespace Tests\Feature;

use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Tests\Concerns\CreatesGroups;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    public function test_logged_in_users_only_see_their_current_group(): void
    {
        $mine = $this->group('Mine');
        $theirs = $this->group('Theirs');
        $this->property($mine, ['code' => 'A']);
        $this->property($theirs, ['code' => 'B']);

        $this->actingAs($this->member($mine));

        $this->assertSame(['A'], Property::pluck('code')->all());
    }

    public function test_a_user_without_a_current_group_sees_nothing(): void
    {
        $this->property($this->group(), ['code' => 'A']);
        $user = $this->member($this->group('Other'));
        $user->forceFill(['current_group_id' => null])->save();

        $this->actingAs($user);

        $this->assertSame(0, Property::count());
    }

    public function test_new_rows_take_the_current_group(): void
    {
        $group = $this->group();
        $this->actingAs($this->member($group));

        $property = $this->property($group, ['group_id' => null, 'code' => 'NEW']);

        $this->assertSame($group->id, $property->group_id);
    }

    public function test_permissions_follow_the_role_in_the_current_group_only(): void
    {
        $a = $this->group('A');
        $b = $this->group('B');
        $user = $this->member($a, 'owner');
        $user->groups()->attach($b->id, ['role' => 'viewer', 'all_properties' => true]);
        $user->load('groups');

        $this->assertTrue(Gate::forUser($user)->allows('view financials'));

        $user->switchGroup($b);
        $user->load('groups');

        $this->assertFalse(Gate::forUser($user)->allows('view financials'), 'owner in A must not count in B');
        $this->assertTrue(Gate::forUser($user)->allows('view dashboard'));
    }

    public function test_finance_role_cannot_manage_users(): void
    {
        $user = $this->member($this->group(), 'finance');

        $this->assertTrue(Gate::forUser($user)->allows('view financials'));
        $this->assertFalse(Gate::forUser($user)->allows('manage users'));
    }

    public function test_users_can_be_limited_to_some_hotels(): void
    {
        $group = $this->group();
        $a = $this->property($group, ['code' => 'A']);
        $this->property($group, ['code' => 'B']);
        $user = $this->member($group, 'viewer', allProperties: false);
        $user->restrictedProperties()->attach($a->id);

        $this->assertSame([$a->id], $user->accessiblePropertyIds()->all());
    }

    public function test_users_cannot_switch_to_a_group_they_do_not_belong_to(): void
    {
        $user = $this->member($this->group('A'));

        $this->expectException(InvalidArgumentException::class);
        $user->switchGroup($this->group('B'));
    }
}
