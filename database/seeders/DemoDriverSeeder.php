<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Driver\Models\Driver;
use App\Modules\Shared\Models\City;
use App\Modules\Vehicle\Models\Vehicle;
use App\Modules\Vehicle\Models\VehicleClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Test için 8 onaylı demo sürücü (6 online + 2 busy) İzmir merkezde.
 * Üretimde otomatik çalışmaz — sadece `php artisan db:seed --class=DemoDriverSeeder`
 * ile elle çağrılır. updateOrCreate kullandığı için idempotent.
 *
 * Sürücü giriş bilgileri (panel testi için):
 *   email: demo.driver.1@ferogo.test ... demo.driver.8@ferogo.test
 *   şifre: demo-driver-1 ... demo-driver-8
 */
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

        $center = [38.4192, 27.1287]; // İzmir Konak

        // Pravatar.cc: stable AI/stock portraits, her id farklı yüz verir (12, 13, 33, 51, 60, 65, 68, 70 = erkek portreleri)
        $drivers = [
            ['name' => 'Mehmet Karaca',   'email' => 'demo.driver.1@ferogo.test', 'phone' => '05321110001', 'class' => 'easy',     'brand' => 'Mercedes',   'model' => 'Vito',     'plate' => '35 AB 1234', 'rating' => 4.95, 'trips' => 1240, 'lat_off' => 0.008,  'lng_off' => -0.011, 'avatar' => 'https://i.pravatar.cc/300?img=12'],
            ['name' => 'Burak Aydın',     'email' => 'demo.driver.2@ferogo.test', 'phone' => '05321110002', 'class' => 'easy',     'brand' => 'Volkswagen', 'model' => 'Passat',   'plate' => '35 KZ 4471', 'rating' => 4.88, 'trips' => 980,  'lat_off' => -0.012, 'lng_off' => 0.015,  'avatar' => 'https://i.pravatar.cc/300?img=13'],
            ['name' => 'Tolga Şen',       'email' => 'demo.driver.3@ferogo.test', 'phone' => '05321110003', 'class' => 'platinum', 'brand' => 'Mercedes',   'model' => 'E-Class',  'plate' => '35 EM 8820', 'rating' => 4.92, 'trips' => 1520, 'lat_off' => 0.018,  'lng_off' => 0.009,  'avatar' => 'https://i.pravatar.cc/300?img=33'],
            ['name' => 'Emre Demir',      'email' => 'demo.driver.4@ferogo.test', 'phone' => '05321110004', 'class' => 'easy',     'brand' => 'Skoda',      'model' => 'Superb',   'plate' => '35 BC 5532', 'rating' => 4.81, 'trips' => 620,  'lat_off' => -0.020, 'lng_off' => -0.018, 'avatar' => 'https://i.pravatar.cc/300?img=51'],
            ['name' => 'Serkan Ozan',     'email' => 'demo.driver.5@ferogo.test', 'phone' => '05321110005', 'class' => 'vip',      'brand' => 'Mercedes',   'model' => 'S-Class',  'plate' => '35 TR 9908', 'rating' => 4.97, 'trips' => 2410, 'lat_off' => 0.005,  'lng_off' => 0.022,  'avatar' => 'https://i.pravatar.cc/300?img=60'],
            ['name' => 'Hakan Yıldız',    'email' => 'demo.driver.6@ferogo.test', 'phone' => '05321110006', 'class' => 'platinum', 'brand' => 'Audi',       'model' => 'A6',       'plate' => '35 FG 3217', 'rating' => 4.85, 'trips' => 870,  'lat_off' => 0.025,  'lng_off' => -0.026, 'avatar' => 'https://i.pravatar.cc/300?img=65'],
            ['name' => 'Cem Bulut',       'email' => 'demo.driver.7@ferogo.test', 'phone' => '05321110007', 'class' => 'easy',     'brand' => 'Renault',    'model' => 'Talisman', 'plate' => '35 HN 1188', 'rating' => 4.78, 'trips' => 510,  'lat_off' => -0.015, 'lng_off' => 0.028,  'avatar' => 'https://i.pravatar.cc/300?img=68'],
            ['name' => 'Murat İşcan',     'email' => 'demo.driver.8@ferogo.test', 'phone' => '05321110008', 'class' => 'platinum', 'brand' => 'BMW',        'model' => '5 Series', 'plate' => '35 PQ 6644', 'rating' => 4.90, 'trips' => 1380, 'lat_off' => 0.028,  'lng_off' => 0.014,  'avatar' => 'https://i.pravatar.cc/300?img=70'],
        ];

        // Sınıfa göre temsili araç galeri fotoğrafları (Unsplash, stable IDs)
        $vehiclePhotosByClass = [
            'easy' => [
                'https://images.unsplash.com/photo-1502877338535-766e1452684a?w=800&q=70&auto=format',
                'https://images.unsplash.com/photo-1494976388531-d1058494cdd8?w=800&q=70&auto=format',
                'https://images.unsplash.com/photo-1542362567-b07e54358753?w=800&q=70&auto=format',
                'https://images.unsplash.com/photo-1485463611174-f302f6a5c1c9?w=800&q=70&auto=format',
                'https://images.unsplash.com/photo-1583267746897-2cf66319ef97?w=800&q=70&auto=format',
            ],
            'platinum' => [
                'https://images.unsplash.com/photo-1555215695-3004980ad54e?w=800&q=70&auto=format',
                'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=800&q=70&auto=format',
                'https://images.unsplash.com/photo-1583121274602-3e2820c69888?w=800&q=70&auto=format',
                'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800&q=70&auto=format',
                'https://images.unsplash.com/photo-1494905998402-395d579af36f?w=800&q=70&auto=format',
                'https://images.unsplash.com/photo-1605559424843-9e4c228bf1c2?w=800&q=70&auto=format',
            ],
            'vip' => [
                'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800&q=70&auto=format',
                'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?w=800&q=70&auto=format',
                'https://images.unsplash.com/photo-1606664515524-ed2f786a0bd6?w=800&q=70&auto=format',
                'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800&q=70&auto=format',
                'https://images.unsplash.com/photo-1563720223185-11003d516935?w=800&q=70&auto=format',
                'https://images.unsplash.com/photo-1631294670132-f8d2b27ab93d?w=800&q=70&auto=format',
            ],
        ];

        foreach ($drivers as $i => $d) {
            /** @var VehicleClass $vClass */
            $vClass = $classBySlug->get($d['class']) ?? $classBySlug->first();

            $user = User::updateOrCreate(
                ['email' => $d['email']],
                [
                    'name'     => $d['name'],
                    'password' => Hash::make('demo-driver-' . ($i + 1)),
                    'type'     => 'driver',
                    'phone'    => $d['phone'],
                    'status'   => 'active',
                    'avatar'   => $d['avatar'],
                ],
            );

            $vehicle = Vehicle::updateOrCreate(
                ['plate' => $d['plate']],
                [
                    'vehicle_class_id'    => $vClass->id,
                    'brand'               => $d['brand'],
                    'model'               => $d['model'],
                    'year_of_manufacture' => 2023,
                    'color'               => 'Siyah',
                    'status'              => 'active',
                    'photos'              => $vehiclePhotosByClass[$d['class']] ?? $vehiclePhotosByClass['easy'],
                ],
            );

            $isBusy = in_array($i, [3, 6], true);

            Driver::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'city_id'                  => $city->id,
                    'current_vehicle_id'       => $vehicle->id,
                    'license_class'            => 'B',
                    'experience_band'          => '5_plus',
                    'commission_rate'          => 15.00,
                    'availability_status'      => $isBusy ? 'busy' : 'online',
                    'current_lat'              => $center[0] + $d['lat_off'],
                    'current_lng'              => $center[1] + $d['lng_off'],
                    'last_location_updated_at' => now(),
                    'approval_status'          => 'approved',
                    'approved_at'              => now(),
                    'rating'                   => $d['rating'],
                    'total_rides'              => $d['trips'],
                ],
            );
        }

        $this->command?->info('  ✓ ' . count($drivers) . ' demo sürücü hazır (giriş: demo.driver.N@ferogo.test / demo-driver-N).');
    }
}
