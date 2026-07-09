<?php

namespace Database\Seeders;

use App\Modules\Vehicle\Models\VehicleMake;
use App\Modules\Vehicle\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Araç marka/model kataloğu — Türkiye pazarındaki yaygın markalar ve modeller.
 * Onboarding'de marka+model SEÇMELİ dropdown olarak kullanılır (serbest metin yerine).
 *
 * Idempotent: updateOrCreate ile tekrar çalıştırılabilir; liste admin panelinden genişletilebilir.
 *
 *   php artisan db:seed --class=Database\\Seeders\\VehicleCatalogSeeder
 */
class VehicleCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $sort => [$make, $models]) {
            $makeModel = VehicleMake::updateOrCreate(
                ['slug' => Str::slug($make)],
                ['name' => $make, 'sort_order' => $sort, 'is_active' => true],
            );

            foreach ($models as $mSort => $model) {
                VehicleModel::updateOrCreate(
                    ['vehicle_make_id' => $makeModel->id, 'name' => $model],
                    ['sort_order' => $mSort, 'is_active' => true],
                );
            }
        }
    }

    /** @return array<int, array{0:string,1:array<int,string>}> */
    private function catalog(): array
    {
        return [
            ['Renault',       ['Clio', 'Symbol', 'Taliant', 'Megane', 'Fluence', 'Talisman', 'Captur', 'Kadjar', 'Koleos', 'Latitude', 'Kangoo', 'Trafic', 'Master']],
            ['Fiat',          ['Egea', 'Egea Cross', 'Linea', 'Punto', 'Panda', 'Tipo', '500', 'Doblo', 'Fiorino', 'Ducato', 'Scudo']],
            ['Volkswagen',    ['Polo', 'Golf', 'Passat', 'Passat Variant', 'Jetta', 'Bora', 'Arteon', 'T-Roc', 'Tiguan', 'Touareg', 'Caddy', 'Transporter', 'Caravelle', 'Multivan', 'Amarok', 'Crafter']],
            ['Ford',          ['Fiesta', 'Focus', 'Mondeo', 'Kuga', 'Puma', 'EcoSport', 'Ranger', 'Transit', 'Transit Custom', 'Tourneo Custom', 'Tourneo Courier', 'Transit Courier', 'Transit Connect']],
            ['Toyota',        ['Corolla', 'Yaris', 'Auris', 'Avensis', 'Camry', 'C-HR', 'RAV4', 'Hilux', 'ProAce', 'Verso', 'Corolla Verso']],
            ['Hyundai',       ['i10', 'i20', 'i30', 'Accent Blue', 'Accent Era', 'Elantra', 'Bayon', 'Kona', 'Tucson', 'Santa Fe', 'Getz', 'H-1', 'Staria']],
            ['Honda',         ['Civic', 'City', 'Jazz', 'Accord', 'CR-V', 'HR-V']],
            ['Opel',          ['Corsa', 'Astra', 'Insignia', 'Vectra', 'Mokka', 'Crossland', 'Grandland', 'Zafira', 'Combo', 'Vivaro']],
            ['Peugeot',       ['208', '301', '308', '407', '508', '2008', '3008', '5008', 'Partner', 'Rifter', 'Expert', 'Traveller', 'Boxer']],
            ['Citroen',       ['C3', 'C4', 'C-Elysee', 'C5', 'Berlingo', 'Jumpy', 'SpaceTourer', 'Jumper']],
            ['Mercedes-Benz', ['A-Serisi', 'B-Serisi', 'C-Serisi', 'E-Serisi', 'S-Serisi', 'CLA', 'CLS', 'GLA', 'GLC', 'GLE', 'Vito', 'V-Serisi', 'Viano', 'Sprinter']],
            ['BMW',           ['1 Serisi', '2 Serisi', '3 Serisi', '4 Serisi', '5 Serisi', '6 Serisi', '7 Serisi', 'X1', 'X3', 'X4', 'X5', 'X6']],
            ['Audi',          ['A1', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'Q2', 'Q3', 'Q5', 'Q7', 'Q8']],
            ['Skoda',         ['Fabia', 'Rapid', 'Scala', 'Octavia', 'Superb', 'Kamiq', 'Karoq', 'Kodiaq']],
            ['Dacia',         ['Sandero', 'Logan', 'Duster', 'Lodgy', 'Dokker', 'Jogger']],
            ['Nissan',        ['Micra', 'Note', 'Qashqai', 'Juke', 'X-Trail', 'Primera', 'NV200']],
            ['Kia',           ['Picanto', 'Rio', 'Ceed', 'Cerato', 'Stonic', 'Sportage', 'Sorento', 'Venga', 'Carnival']],
            ['Seat',          ['Ibiza', 'Leon', 'Toledo', 'Cordoba', 'Arona', 'Ateca', 'Alhambra']],
            ['Volvo',         ['S60', 'S90', 'V40', 'V60', 'XC40', 'XC60', 'XC90']],
            ['Mazda',         ['2', '3', '6', 'CX-3', 'CX-5']],
            ['Mitsubishi',    ['Lancer', 'Space Star', 'ASX', 'Outlander', 'L200']],
            ['Suzuki',        ['Swift', 'Baleno', 'SX4', 'Vitara', 'Grand Vitara']],
            ['Chevrolet',     ['Aveo', 'Cruze', 'Lacetti', 'Captiva', 'Kalos']],
            ['Alfa Romeo',    ['Giulietta', 'Giulia', 'Stelvio', '159']],
            ['Jeep',          ['Renegade', 'Compass', 'Cherokee', 'Wrangler']],
            ['Land Rover',    ['Range Rover', 'Range Rover Evoque', 'Discovery', 'Defender']],
            ['MG',            ['MG3', 'MG4', 'ZS', 'HS']],
            ['Chery',         ['Tiggo 7', 'Tiggo 8', 'Alia']],
            ['Tesla',         ['Model 3', 'Model S', 'Model Y', 'Model X']],
            ['Togg',          ['T10X', 'T10F']],
            ['Isuzu',         ['D-Max', 'Novociti', 'Turkuaz']],
            ['Iveco',         ['Daily']],
            ['Porsche',       ['Panamera', 'Cayenne', 'Macan']],
            ['Mini',          ['Cooper', 'Countryman', 'Clubman']],
            ['DS',            ['DS 3', 'DS 4', 'DS 7']],
            ['Subaru',        ['Impreza', 'Forester', 'XV']],
            ['Lexus',         ['IS', 'ES', 'NX', 'RX', 'UX']],
            ['Maxus',         ['eDeliver', 'T60', 'G10']],
        ];
    }
}
