<?php

namespace Database\Seeders;

use App\Models\HotelGroup;
use App\Models\PmsProvider;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local demo data matching the Fandaqah sample reports in docs/samples/fandaqah-pms
 * ("Address Hotel" / "فندق العنوان", PMS team 2411). Try:
 *
 *   php artisan fgre:ingest-file docs/samples/fandaqah-pms/fandaqah-sample-manager-flash-20261119-en.json --from=reports@fandaqah.example
 */
class DemoGroupSeeder extends Seeder
{
    public const SENDER = 'reports@fandaqah.example';

    public function run(): void
    {
        $group = HotelGroup::updateOrCreate(['slug' => 'demo-group'], [
            'name' => 'Demo Hotel Group',
            'name_ar' => 'مجموعة الفنادق التجريبية',
        ]);

        $fandaqah = PmsProvider::where('code', 'fandaqah')->firstOrFail();

        Property::forGroup($group->id)->updateOrCreate(['group_id' => $group->id, 'code' => 'ADDR'], [
            'pms_provider_id' => $fandaqah->id,
            'name' => 'Address Hotel',
            'name_ar' => 'فندق العنوان',
            'pms_property_ref' => '2411',
            'total_rooms' => 66,
            'allowed_sender_emails' => [self::SENDER],
        ]);

        $owner = User::updateOrCreate(['email' => 'owner@demo.example'], [
            'name' => 'Demo Owner',
            'password' => 'password',
        ]);
        $owner->groups()->syncWithoutDetaching([$group->id => ['role' => 'owner', 'all_properties' => true]]);
        $owner->forceFill(['current_group_id' => $group->id])->save();
    }
}
