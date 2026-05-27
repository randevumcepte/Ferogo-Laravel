<?php

namespace Database\Seeders;

use App\Modules\Vehicle\Models\VehicleClass;
use Illuminate\Database\Seeder;

class VehicleClassSeeder extends Seeder
{
    public function run(): void
    {
        // Sade km-bazlı tarife (Lets Go Easy modeli).
        // per_minute_fare = 0 — süre fiyatlandırması yok.
        // per_km_fare'i hafifçe yukarı kalibre ettim (eski 18/28/45 → 22/35/55)
        // ki ortalama yolculukta toplam tutar benzer kalsın.
        $classes = [
            [
                'slug' => 'easy',
                'name' => 'Easy',
                'description' => 'Konforlu ve ekonomik şehir içi yolculuk',
                'max_passengers' => 4,
                'max_luggage' => 3,
                'base_fare' => 50.00,
                'per_km_fare' => 22.00,
                'per_minute_fare' => 0.00,
                'minimum_fare' => 150.00,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'slug' => 'platinum',
                'name' => 'Platinum',
                'description' => 'Premium araç ve profesyonel sürücü',
                'max_passengers' => 4,
                'max_luggage' => 3,
                'base_fare' => 100.00,
                'per_km_fare' => 35.00,
                'per_minute_fare' => 0.00,
                'minimum_fare' => 250.00,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'slug' => 'vip',
                'name' => 'VIP',
                'description' => 'Lüks araç filosu, en üst düzey konfor',
                'max_passengers' => 6,
                'max_luggage' => 5,
                'base_fare' => 200.00,
                'per_km_fare' => 55.00,
                'per_minute_fare' => 0.00,
                'minimum_fare' => 500.00,
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($classes as $class) {
            VehicleClass::updateOrCreate(['slug' => $class['slug']], $class);
        }
    }
}
