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
            DriverCategorySeeder::class,      // Sürücü kategorileri (otomobil/sari_taksi/motosiklet)
            VehicleCatalogSeeder::class,      // Marka + model kataloğu (kategori ilişkili)
            ExtraSeeder::class,
            PricingRuleSeeder::class,
            LegalTextVersionSeeder::class,
        ]);
    }
}
