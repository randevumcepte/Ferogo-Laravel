<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Driver\Models\Driver;
use App\Modules\Shared\Models\City;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\Vehicle\Models\VehicleClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDriverSeeder extends Seeder
{
    public function run(): void
    {
        $city = City::where('slug', 'izmir')->first();
        if (! $city) {
            $this->command?->warn('İzmir şehri bulunamadı, DemoDriverSeeder atlandı.');
            return;
        }

        $classBySlug = VehicleClass::query()
            ->whereIn('slug', ['easy', 'platinum', 'vip'])
            ->get()
            ->keyBy('slug');

        if ($classBySlug->isEmpty()) {
            $this->command?->warn('Vehicle class bulunamadı, DemoDriverSeeder atlandı.');
            return;
        }

        // İzmir merkez: 38.4192, 27.1287 — sürücüleri 1-4 km dağılımla
        $center = [38.4192, 27.1287];

        $drivers = [
            ['name' => 'Mehmet Karaca',   'email' => 'demo.driver.1@ferogo.test', 'phone' => '05321110001', 'class' => 'easy',     'brand' => 'Mercedes', 'model' => 'Vito',  'plate' => '35 AB 1234', 'rating' => 4.95, 'trips' => 1240, 'lat_off' => 0.008,  'lng_off' => -0.011],
            ['name' => 'Burak Aydın',     'email' => 'demo.driver.2@ferogo.test', 'phone' => '05321110002', 'class' => 'easy',     'brand' => 'Volkswagen', 'model' => 'Passat', 'plate' => '35 KZ 4471', 'rating' => 4.88, 'trips' => 980,  'lat_off' => -0.012, 'lng_off' => 0.015],
            ['name' => 'Tolga Şen',       'email' => 'demo.driver.3@ferogo.test', 'phone' => '05321110003', 'class' => 'platinum', 'brand' => 'Mercedes', 'model' => 'E-Class', 'plate' => '35 EM 8820', 'rating' => 4.92, 'trips' => 1520, 'lat_off' => 0.018,  'lng_off' => 0.009],
            ['name' => 'Emre Demir',      'email' => 'demo.driver.4@ferogo.test', 'phone' => '05321110004', 'class' => 'easy',     'brand' => 'Skoda',    'model' => 'Superb',  'plate' => '35 BC 5532', 'rating' => 4.81, 'trips' => 620,  'lat_off' => -0.020, 'lng_off' => -0.018],
            ['name' => 'Serkan Ozan',     'email' => 'demo.driver.5@ferogo.test', 'phone' => '05321110005', 'class' => 'vip',      'brand' => 'Mercedes', 'model' => 'S-Class', 'plate' => '35 TR 9908', 'rating' => 4.97, 'trips' => 2410, 'lat_off' => 0.005,  'lng_off' => 0.022],
            ['name' => 'Hakan Yıldız',    'email' => 'demo.driver.6@ferogo.test', 'phone' => '05321110006', 'class' => 'platinum', 'brand' => 'Audi',     'model' => 'A6',      'plate' => '35 FG 3217', 'rating' => 4.85, 'trips' => 870,  'lat_off' => 0.025,  'lng_off' => -0.026],
            ['name' => 'Cem Bulut',       'email' => 'demo.driver.7@ferogo.test', 'phone' => '05321110007', 'class' => 'easy',     'brand' => 'Renault',  'model' => 'Talisman', 'plate' => '35 HN 1188', 'rating' => 4.78, 'trips' => 510,  'lat_off' => -0.015, 'lng_off' => 0.028],
            ['name' => 'Murat İşcan',     'email' => 'demo.driver.8@ferogo.test', 'phone' => '05321110008', 'class' => 'platinum', 'brand' => 'BMW',      'model' => '5 Series', 'plate' => '35 PQ 6644', 'rating' => 4.90, 'trips' => 1380, 'lat_off' => 0.028,  'lng_off' => 0.014],
        ];

        foreach ($drivers as $i => $d) {
            /** @var VehicleClass|null $vClass */
            $vClass = $classBySlug->get($d['class']) ?? $classBySlug->first();

            $user = User::updateOrCreate(
                ['email' => $d['email']],
                [
                    'name'     => $d['name'],
                    'password' => Hash::make('demo-driver-' . ($i + 1)),
                    'type'     => 'driver',
                    'phone'    => $d['phone'],
                    'status'   => 'active',
                ],
            );

            $vehicle = Vehicle::updateOrCreate(
                ['plate' => $d['plate']],
                [
                    'vehicle_class_id'     => $vClass->id,
                    'brand'                => $d['brand'],
                    'model'                => $d['model'],
                    'year_of_manufacture'  => 2023,
                    'color'                => 'Siyah',
                    'status'               => 'active',
                ],
            );

            // 8 sürücüden 2'sini "busy" yap, gerisi "online"
            $isBusy = in_array($i, [3, 6], true);

            Driver::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'city_id'              => $city->id,
                    'current_vehicle_id'   => $vehicle->id,
                    'license_class'        => 'B',
                    'experience_band'      => '5_plus',
                    'commission_rate'      => 15.00,
                    'availability_status'  => $isBusy ? 'busy' : 'online',
                    'current_lat'          => $center[0] + $d['lat_off'],
                    'current_lng'          => $center[1] + $d['lng_off'],
                    'last_location_updated_at' => now(),
                    'approval_status'      => 'approved',
                    'approved_at'          => now(),
                    'rating'               => $d['rating'],
                    'total_rides'          => $d['trips'],
                ],
            );
        }

        $this->command?->info('  ✓ ' . count($drivers) . ' demo sürücü oluşturuldu (İzmir).');
    }
}
