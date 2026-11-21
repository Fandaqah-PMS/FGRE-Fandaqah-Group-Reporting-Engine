<?php

namespace Database\Seeders;

use App\Models\PmsProvider;
use Illuminate\Database\Seeder;

class PmsProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            ['code' => 'fandaqah', 'name' => 'Fandaqah PMS', 'supports_mcp' => true],
            ['code' => 'nazeel', 'name' => 'Nazeel', 'supports_mcp' => false],
            ['code' => 'opera', 'name' => 'Oracle Opera', 'supports_mcp' => false],
        ];

        foreach ($providers as $provider) {
            PmsProvider::updateOrCreate(['code' => $provider['code']], $provider);
        }
    }
}
