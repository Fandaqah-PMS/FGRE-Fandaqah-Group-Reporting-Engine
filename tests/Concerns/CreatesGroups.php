<?php

namespace Tests\Concerns;

use App\Models\HotelGroup;
use App\Models\PmsProvider;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Str;

trait CreatesGroups
{
    protected const SENDER = 'reports@fandaqah-pms.example';

    protected function group(string $name = 'Riyadh Hotels'): HotelGroup
    {
        return HotelGroup::create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::random(5)]);
    }

    protected function property(?HotelGroup $group = null, array $attributes = []): Property
    {
        $group ??= $this->group();
        $provider = PmsProvider::firstOrCreate(['code' => 'fandaqah'], ['name' => 'Fandaqah PMS']);

        return Property::forGroup($group->id)->create(array_merge([
            'group_id' => $group->id,
            'pms_provider_id' => $provider->id,
            'name' => 'Fandaqah Riyadh',
            'name_ar' => 'فندقة الرياض',
            'code' => 'RUH01',
            'allowed_sender_emails' => [self::SENDER],
        ], $attributes));
    }

    protected function member(HotelGroup $group, string $role = 'owner', bool $allProperties = true): User
    {
        $user = User::factory()->create();
        $user->groups()->attach($group->id, ['role' => $role, 'all_properties' => $allProperties]);
        $user->forceFill(['current_group_id' => $group->id])->save();

        return $user->fresh();
    }
}
