<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CitySeeder::class,
            VehicleClassSeeder::class,
            VehicleCatalogSeeder::class,
            ExtraSeeder::class,
            PricingRuleSeeder::class,
            LegalTextVersionSeeder::class,
        ]);
    }
}
