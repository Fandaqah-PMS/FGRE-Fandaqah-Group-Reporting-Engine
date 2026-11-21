<?php

namespace Database\Seeders;

use App\Models\PmsProvider;
use Illuminate\Database\Seeder;

class PmsProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            ['code' => 'fandaqah', 'name' => 'Fandaqah PMS'],
            ['code' => 'nazeel', 'name' => 'Nazeel'],
            ['code' => 'opera', 'name' => 'Oracle Opera'],
        ];

        foreach ($providers as $provider) {
            PmsProvider::updateOrCreate(['code' => $provider['code']], $provider);
        }
    }
}
