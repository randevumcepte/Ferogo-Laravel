<?php

namespace Database\Seeders;

use App\Modules\Shared\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ['name' => 'İzmir',    'slug' => 'izmir',    'center_lat' => 38.4192, 'center_lng' => 27.1287, 'is_active' => true,  'sort_order' => 1],
            ['name' => 'İstanbul', 'slug' => 'istanbul', 'center_lat' => 41.0082, 'center_lng' => 28.9784, 'is_active' => false, 'sort_order' => 2],
            ['name' => 'Antalya',  'slug' => 'antalya',  'center_lat' => 36.8969, 'center_lng' => 30.7133, 'is_active' => false, 'sort_order' => 3],
            ['name' => 'Bursa',    'slug' => 'bursa',    'center_lat' => 40.1828, 'center_lng' => 29.0665, 'is_active' => false, 'sort_order' => 4],
        ];

        foreach ($cities as $city) {
            City::updateOrCreate(
                ['slug' => $city['slug']],
                array_merge($city, ['country_code' => 'TR', 'timezone' => 'Europe/Istanbul']),
            );
        }
    }
}
