<?php

namespace Database\Seeders;

use App\Modules\Pricing\Models\PricingRule;
use App\Modules\Shared\Models\City;
use App\Modules\Vehicle\Models\VehicleClass;
use Illuminate\Database\Seeder;

class PricingRuleSeeder extends Seeder
{
    public function run(): void
    {
        $izmir = City::where('slug', 'izmir')->first();
        if (! $izmir) {
            $this->command->warn('İzmir bulunamadı, CitySeeder once çalışmalı.');
            return;
        }

        foreach (VehicleClass::all() as $class) {
            PricingRule::updateOrCreate(
                ['city_id' => $izmir->id, 'vehicle_class_id' => $class->id],
                [
                    'base_fare' => $class->base_fare,
                    'per_km_fare' => $class->per_km_fare,
                    'per_minute_fare' => 0.00,   // Süre kalemi kapalı (Lets Go Easy modeli)
                    'minimum_fare' => $class->minimum_fare,
                    'night_multiplier' => 1.50,
                    'night_start' => '22:00:00',
                    'night_end' => '06:00:00',
                    'peak_multiplier' => 1.25,
                    'is_active' => true,
                ]
            );
        }
    }
}
