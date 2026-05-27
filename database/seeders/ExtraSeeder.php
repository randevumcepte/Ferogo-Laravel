<?php

namespace Database\Seeders;

use App\Modules\Pricing\Models\Extra;
use Illuminate\Database\Seeder;

class ExtraSeeder extends Seeder
{
    public function run(): void
    {
        $extras = [
            ['slug' => 'baby_seat',    'name' => 'Bebek Koltuğu',     'type' => 'seat',    'price' => 250.00, 'per_unit' => true,  'max_quantity' => 3, 'sort_order' => 1, 'description' => null],
            ['slug' => 'child_seat',   'name' => 'Çocuk Koltuğu',     'type' => 'seat',    'price' => 250.00, 'per_unit' => true,  'max_quantity' => 3, 'sort_order' => 2, 'description' => null],
            ['slug' => 'booster_seat', 'name' => 'Yükseltici (7+ yaş)', 'type' => 'seat',  'price' => 250.00, 'per_unit' => true,  'max_quantity' => 3, 'sort_order' => 3, 'description' => null],
            ['slug' => 'pet',          'name' => 'Evcil Hayvan',      'type' => 'pet',     'price' => 100.00, 'per_unit' => false, 'max_quantity' => 1, 'sort_order' => 4, 'description' => 'Küçük ırk, kucak veya pet box ile. Aşı karnesi zorunlu.'],
            ['slug' => 'standard_package',      'name' => 'Standart Paket',         'type' => 'package', 'price' => 0.00,    'per_unit' => false, 'max_quantity' => 1, 'sort_order' => 5, 'description' => 'Su, peçete, ıslak mendil, şarj kablosu, küçük şekerlemeler'],
            ['slug' => 'premium_package',       'name' => 'Premium Paket',          'type' => 'package', 'price' => 500.00,  'per_unit' => false, 'max_quantity' => 1, 'sort_order' => 6, 'description' => 'Standart + soda, soft içecekler (Kola, Fanta, Gazoz), çerez'],
            ['slug' => 'premium_extra_package', 'name' => 'Premium Ekstra Paket',   'type' => 'package', 'price' => 1000.00, 'per_unit' => false, 'max_quantity' => 1, 'sort_order' => 7, 'description' => 'Premium + soğuk kahve, enerji içeceği, çikolata, Haribo şekerleme'],
        ];

        foreach ($extras as $extra) {
            Extra::updateOrCreate(
                ['slug' => $extra['slug']],
                array_merge($extra, ['is_active' => true]),
            );
        }
    }
}
