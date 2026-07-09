<?php

namespace Database\Seeders;

use App\Modules\Vehicle\Models\VehicleMake;
use App\Modules\Vehicle\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Araç marka/model kataloğu — Türkiye pazarındaki yaygın markalar ve modeller.
 * Kategoriye göre filtrelenir (otomobil / sari_taksi / motosiklet).
 *
 * Onboarding'de marka+model SEÇMELİ dropdown olarak kullanılır (serbest metin yerine).
 * "Modelim yok" senaryosu için: sürücü serbest metin `vehicle_info` alanına yazabilir.
 *
 * Idempotent: updateOrCreate ile tekrar çalıştırılabilir.
 *
 *   php artisan db:seed --class=Database\\Seeders\\VehicleCatalogSeeder
 */
class VehicleCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Eski kayıtlarda category_slug NULL olan model varsa — varsayılan olarak
        // 'otomobil' ata. Bu yalnızca ilk kez migration'ı çalıştırdıkta gerçekleşir.
        VehicleModel::whereNull('category_slug')->update(['category_slug' => 'otomobil']);

        $sort = 0;
        foreach ($this->autoMakes() as [$make, $models]) {
            $this->seedMake($make, ['otomobil', 'sari_taksi'], $models, 'otomobil', $sort++);
        }

        // Sarı taksi genelde otomobil markaları ama T plakalı — model kataloğu
        // aynı; ekstra bir şey yapmaya gerek yok, applicable_categories ikisini
        // de kapsıyor.
        // Sarı taksi için MODEL kayıtları da 'sari_taksi' kategoride ayrıca
        // yaratıyoruz ki model dropdown'u filtreleyebilelim.
        foreach ($this->autoMakes() as [$make, $models]) {
            $this->seedModels($make, 'sari_taksi', $models);
        }

        foreach ($this->motorMakes() as [$make, $models]) {
            $this->seedMake($make, ['motosiklet'], $models, 'motosiklet', $sort++);
        }

        $this->command?->info('  ✓ Araç kataloğu seed edildi (otomobil + sarı taksi + motosiklet).');
    }

    /**
     * @param array<int, string> $categories
     * @param array<int, string> $models
     */
    private function seedMake(string $makeName, array $categories, array $models, string $primaryCategory, int $sort): void
    {
        $slug = Str::slug($makeName);

        // Mevcut kayıt varsa applicable_categories'i EZME — merge et.
        // Böylece Honda otomobil için ['otomobil','sari_taksi'], sonra motor için
        // eklenince ['otomobil','sari_taksi','motosiklet'] olur.
        $existing = VehicleMake::where('slug', $slug)->first();
        $mergedCategories = array_values(array_unique(array_merge(
            (array) ($existing?->applicable_categories ?? []),
            $categories,
        )));

        $make = VehicleMake::updateOrCreate(
            ['slug' => $slug],
            [
                'name'                  => $makeName,
                'applicable_categories' => $mergedCategories,
                'sort_order'            => $existing?->sort_order ?? $sort,
                'is_active'             => true,
            ],
        );

        $this->seedModels($makeName, $primaryCategory, $models, $make);
    }

    private function seedModels(string $makeName, string $categorySlug, array $models, ?VehicleMake $make = null): void
    {
        $make = $make ?? VehicleMake::where('slug', Str::slug($makeName))->first();
        if (! $make) return;

        foreach ($models as $sort => $modelName) {
            VehicleModel::updateOrCreate(
                [
                    'vehicle_make_id' => $make->id,
                    'name'            => $modelName,
                    'category_slug'   => $categorySlug,
                ],
                ['sort_order' => $sort, 'is_active' => true],
            );
        }
    }

    /**
     * Otomobil markaları (aynıları sarı taksi kategorisinde de görünür).
     * @return array<int, array{0:string,1:array<int,string>}>
     */
    private function autoMakes(): array
    {
        return [
            ['Renault',       ['Clio', 'Symbol', 'Taliant', 'Megane', 'Fluence', 'Talisman', 'Captur', 'Kadjar', 'Koleos', 'Latitude', 'Kangoo', 'Trafic', 'Master', 'Duster', 'Austral']],
            ['Fiat',          ['Egea', 'Egea Cross', 'Linea', 'Punto', 'Panda', 'Tipo', '500', 'Doblo', 'Fiorino', 'Ducato', 'Scudo', '500L', '500X']],
            ['Volkswagen',    ['Polo', 'Golf', 'Passat', 'Passat Variant', 'Jetta', 'Bora', 'Arteon', 'T-Roc', 'Tiguan', 'Touareg', 'Caddy', 'Transporter', 'Caravelle', 'Multivan', 'Amarok', 'Crafter', 'ID.4', 'ID.5']],
            ['Ford',          ['Fiesta', 'Focus', 'Mondeo', 'Kuga', 'Puma', 'EcoSport', 'Ranger', 'Transit', 'Transit Custom', 'Tourneo Custom', 'Tourneo Courier', 'Transit Courier', 'Transit Connect', 'Escort', 'Cargo']],
            ['Toyota',        ['Corolla', 'Yaris', 'Auris', 'Avensis', 'Camry', 'C-HR', 'RAV4', 'Hilux', 'ProAce', 'Verso', 'Corolla Verso', 'Prius', 'Land Cruiser', 'Aygo']],
            ['Hyundai',       ['i10', 'i20', 'i30', 'Accent Blue', 'Accent Era', 'Elantra', 'Bayon', 'Kona', 'Tucson', 'Santa Fe', 'Getz', 'H-1', 'Staria', 'Ioniq', 'Ioniq 5']],
            ['Honda',         ['Civic', 'City', 'Jazz', 'Accord', 'CR-V', 'HR-V', 'e:Ny1']],
            ['Opel',          ['Corsa', 'Astra', 'Insignia', 'Vectra', 'Mokka', 'Crossland', 'Grandland', 'Zafira', 'Combo', 'Vivaro', 'Movano']],
            ['Peugeot',       ['208', '301', '308', '407', '508', '2008', '3008', '5008', 'Partner', 'Rifter', 'Expert', 'Traveller', 'Boxer', '206', '207']],
            ['Citroen',       ['C3', 'C4', 'C-Elysee', 'C5', 'Berlingo', 'Jumpy', 'SpaceTourer', 'Jumper', 'C3 Aircross', 'C5 Aircross']],
            ['Mercedes-Benz', ['A-Serisi', 'B-Serisi', 'C-Serisi', 'E-Serisi', 'S-Serisi', 'CLA', 'CLS', 'GLA', 'GLB', 'GLC', 'GLE', 'GLS', 'Vito', 'V-Serisi', 'Viano', 'Sprinter', 'EQE', 'EQS', 'EQC']],
            ['BMW',           ['1 Serisi', '2 Serisi', '3 Serisi', '4 Serisi', '5 Serisi', '6 Serisi', '7 Serisi', 'X1', 'X2', 'X3', 'X4', 'X5', 'X6', 'X7', 'iX', 'i4', 'i5', 'i7']],
            ['Audi',          ['A1', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'Q2', 'Q3', 'Q4 e-tron', 'Q5', 'Q7', 'Q8', 'e-tron GT']],
            ['Skoda',         ['Fabia', 'Rapid', 'Scala', 'Octavia', 'Superb', 'Kamiq', 'Karoq', 'Kodiaq', 'Enyaq']],
            ['Dacia',         ['Sandero', 'Logan', 'Duster', 'Lodgy', 'Dokker', 'Jogger', 'Spring', 'Bigster']],
            ['Nissan',        ['Micra', 'Note', 'Qashqai', 'Juke', 'X-Trail', 'Primera', 'NV200', 'Leaf', 'Ariya', 'Navara']],
            ['Kia',           ['Picanto', 'Rio', 'Ceed', 'Cerato', 'Stonic', 'Sportage', 'Sorento', 'Venga', 'Carnival', 'EV6', 'Niro']],
            ['Seat',          ['Ibiza', 'Leon', 'Toledo', 'Cordoba', 'Arona', 'Ateca', 'Alhambra', 'Tarraco']],
            ['Cupra',         ['Formentor', 'Leon', 'Ateca', 'Born', 'Tavascan']],
            ['Volvo',         ['S60', 'S90', 'V40', 'V60', 'V90', 'XC40', 'XC60', 'XC90', 'C40 Recharge', 'EX30']],
            ['Mazda',         ['2', '3', '6', 'CX-3', 'CX-30', 'CX-5', 'CX-60', 'MX-30']],
            ['Mitsubishi',    ['Lancer', 'Space Star', 'ASX', 'Outlander', 'L200', 'Eclipse Cross']],
            ['Suzuki',        ['Swift', 'Baleno', 'SX4', 'S-Cross', 'Vitara', 'Grand Vitara', 'Ignis', 'Jimny']],
            ['Chevrolet',     ['Aveo', 'Cruze', 'Lacetti', 'Captiva', 'Kalos', 'Spark', 'Trailblazer']],
            ['Alfa Romeo',    ['Giulietta', 'Giulia', 'Stelvio', '159', 'Tonale', 'MiTo']],
            ['Jeep',          ['Renegade', 'Compass', 'Cherokee', 'Wrangler', 'Grand Cherokee', 'Avenger']],
            ['Land Rover',    ['Range Rover', 'Range Rover Evoque', 'Range Rover Velar', 'Range Rover Sport', 'Discovery', 'Discovery Sport', 'Defender']],
            ['MG',            ['MG3', 'MG4', 'MG5', 'MG6', 'ZS', 'HS', 'Marvel R']],
            ['Chery',         ['Tiggo 7 Pro', 'Tiggo 8 Pro', 'Alia', 'Omoda 5']],
            ['Tesla',         ['Model 3', 'Model S', 'Model Y', 'Model X', 'Cybertruck']],
            ['Togg',          ['T10X', 'T10F']],
            ['Isuzu',         ['D-Max', 'Novociti', 'Turkuaz', 'Roybus']],
            ['Iveco',         ['Daily', 'S-Way']],
            ['Porsche',       ['Panamera', 'Cayenne', 'Macan', 'Taycan', '911', '718']],
            ['Mini',          ['Cooper', 'Cooper S', 'Countryman', 'Clubman', 'Aceman']],
            ['DS',            ['DS 3', 'DS 4', 'DS 7', 'DS 9']],
            ['Subaru',        ['Impreza', 'Forester', 'XV', 'Outback', 'BRZ']],
            ['Lexus',         ['IS', 'ES', 'NX', 'RX', 'UX', 'LS', 'LX', 'LC']],
            ['Maxus',         ['eDeliver 3', 'eDeliver 5', 'eDeliver 9', 'T60', 'T90', 'G10']],
            ['BYD',           ['Atto 3', 'Han', 'Seal', 'Dolphin', 'Song Plus']],
            ['Zeekr',         ['001', '007', 'X', '009']],
            ['Skywell',       ['ET5']],
            ['Aiways',        ['U5', 'U6']],
            ['Ora',           ['Cat', 'Funky Cat']],
            ['Chrysler',      ['300C', 'Voyager']],
            ['Dodge',         ['Charger', 'Challenger']],
            ['SsangYong',     ['Actyon', 'Korando', 'Rexton', 'Tivoli']],
            ['Tata',          ['Nexon', 'Punch', 'Tiago']],
            ['GAZ',           ['Volga']],
            ['UAZ',           ['Patriot']],
            ['Lada',          ['Vesta', 'Granta', 'Niva']],
            ['Proton',        ['Saga', 'Persona']],
            ['Perodua',       ['Bezza']],
            ['Aixam',         ['City', 'Coupe']],
            ['Piaggio',       ['Porter']],
            ['Great Wall',    ['Wingle', 'Poer', 'Ora']],
            ['Haval',         ['H6', 'Jolion', 'Dargo']],
            ['JAC',           ['S3', 'S5', 'T6']],
            ['DFSK',          ['C31', 'C35', 'K01']],
            ['Karsan',        ['Jest', 'Atak', 'Star', 'Bozankaya']],
            ['TEMSA',         ['MD9', 'Prestij', 'Diamond']],
            ['Otokar',        ['Sultan', 'Kent', 'Territo']],
        ];
    }

    /**
     * Motosiklet markaları — Türkiye pazarında yaygın.
     * @return array<int, array{0:string,1:array<int,string>}>
     */
    private function motorMakes(): array
    {
        return [
            ['Honda',          ['CBR 250R', 'CBR 500R', 'CBR 650R', 'CBR 1000RR', 'CB 125R', 'CB 500F', 'CB 650R', 'CB 750 Hornet', 'PCX 125', 'PCX 150', 'PCX 160', 'Forza 125', 'Forza 350', 'ADV 350', 'X-ADV', 'Africa Twin', 'NC 750X', 'Transalp', 'Rebel 500', 'Grom', 'Wave 110', 'Innova', 'Activa']],
            ['Yamaha',         ['MT-03', 'MT-07', 'MT-09', 'MT-10', 'MT-125', 'YZF-R3', 'YZF-R6', 'YZF-R7', 'YZF-R1', 'YZF-R125', 'XSR 700', 'XSR 900', 'Tracer 7', 'Tracer 9', 'Ténéré 700', 'FZ 6', 'FZ 8', 'XMAX 125', 'XMAX 250', 'XMAX 300', 'NMAX 125', 'NMAX 155', 'TMAX 560', 'Aerox 155', 'Fazer 250', 'YBR 125', 'Crypton', 'PW 50']],
            ['Kawasaki',       ['Z125', 'Z250', 'Z400', 'Z650', 'Z900', 'Z1000', 'Ninja 125', 'Ninja 250', 'Ninja 300', 'Ninja 400', 'Ninja 650', 'Ninja ZX-6R', 'Ninja ZX-10R', 'Ninja H2', 'Versys 300', 'Versys 650', 'Versys 1000', 'Vulcan S', 'Vulcan 900', 'W800', 'KLR 650', 'KX 250', 'KX 450']],
            ['Suzuki',         ['GSX-R125', 'GSX-R600', 'GSX-R1000', 'GSX-S125', 'GSX-S1000', 'V-Strom 250', 'V-Strom 650', 'V-Strom 1050', 'SV 650', 'GN 125', 'DR 200', 'Address 125', 'Burgman 200', 'Burgman 400', 'Hayabusa']],
            ['KTM',            ['125 Duke', '200 Duke', '250 Duke', '390 Duke', '790 Duke', '890 Duke', '1290 Super Duke', 'RC 125', 'RC 200', 'RC 390', '390 Adventure', '890 Adventure', '1290 Super Adventure', '450 EXC', '250 SX-F', 'Freeride']],
            ['BMW Motorrad',   ['G 310 R', 'G 310 GS', 'F 750 GS', 'F 850 GS', 'F 900 R', 'F 900 XR', 'R 1250 GS', 'R 1250 GS Adventure', 'R 1250 RS', 'R 1250 RT', 'S 1000 RR', 'S 1000 R', 'S 1000 XR', 'K 1600 GT', 'CE 04', 'C 400 GT', 'C 400 X', 'M 1000 RR']],
            ['Ducati',         ['Monster', 'Monster Plus', 'Scrambler', 'Panigale V2', 'Panigale V4', 'Streetfighter V2', 'Streetfighter V4', 'Multistrada V4', 'Diavel V4', 'Hypermotard 950', 'DesertX']],
            ['Aprilia',        ['SR 50', 'SR 125', 'SR Max', 'RS 125', 'RS 660', 'RSV4', 'Tuono 660', 'Tuono V4', 'Shiver 900', 'SXR 160']],
            ['Vespa',          ['Primavera 125', 'Primavera 150', 'Sprint 125', 'Sprint 150', 'GTS 300', 'GTS Super', 'Elettrica', 'LX 125', 'LX 150', 'PX 125', 'PX 150']],
            ['Piaggio',        ['Beverly 300', 'Beverly 400', 'Liberty 125', 'Liberty 150', 'Medley 125', 'MP3 300', 'MP3 500', 'X-Evo 250', 'Zip 50']],
            ['Kymco',          ['Agility 125', 'Agility 200', 'People S 125', 'People S 200', 'Xciting 400', 'AK 550', 'Downtown 350', 'Racing 200', 'Super 8 125']],
            ['SYM',            ['Symphony 125', 'Symphony 150', 'Jet 14', 'Fiddle', 'Cruisym 300', 'Maxsym 400', 'GTS 300', 'ADX 125']],
            ['Peugeot',        ['Kisbee', 'Django', 'Metropolis', 'Speedfight 4', 'Tweet 125', 'Streetzone 50']],
            ['Bajaj',          ['Pulsar 125', 'Pulsar 150', 'Pulsar 180', 'Pulsar 200 NS', 'Pulsar RS 200', 'Dominar 400', 'Boxer 125', 'CT 100']],
            ['TVS',            ['Apache RTR 160', 'Apache RTR 200', 'Apache RR 310', 'Ntorq 125', 'Sport 100', 'Star City']],
            ['Royal Enfield',  ['Meteor 350', 'Classic 350', 'Hunter 350', 'Bullet 350', 'Himalayan 411', 'Himalayan 450', 'Continental GT 650', 'Interceptor 650', 'Super Meteor 650']],
            ['Benelli',        ['TNT 125', 'TNT 135', 'TNT 300', 'TNT 600', '752S', 'TRK 502', 'TRK 502X', 'TRK 702', 'Leoncino 500', 'Leoncino 800', 'Imperiale 400']],
            ['CFMoto',         ['150NK', '250NK', '250SR', '300NK', '300SR', '450NK', '450SR', '650NK', '650MT', '700CL-X', '800MT', '1250TR-G']],
            ['Zontes',         ['ZT 125', 'ZT 155', 'ZT 250', 'ZT 310', 'ZT 350', 'ZT 703']],
            ['QJ Motor',       ['SRK 250', 'SRK 400', 'SRK 600', 'SRV 300', 'SRT 250']],
            ['Harley-Davidson',['Iron 883', 'Iron 1200', 'Forty-Eight', 'Fat Bob', 'Fat Boy', 'Street Bob', 'Sportster S', 'Nightster', 'Road King', 'Road Glide', 'Street Glide', 'Pan America 1250']],
            ['MV Agusta',      ['Brutale 800', 'Brutale 1000', 'Dragster 800', 'F3 800', 'Turismo Veloce']],
            ['Triumph',        ['Street Triple', 'Speed Triple', 'Trident 660', 'Tiger 660', 'Tiger 900', 'Tiger 1200', 'Bonneville T100', 'Bonneville T120', 'Scrambler 900', 'Speed Twin', 'Rocket 3']],
            ['Husqvarna',      ['Svartpilen 125', 'Svartpilen 250', 'Svartpilen 401', 'Vitpilen 401', 'Norden 901', '701 Enduro', '701 Supermoto']],
            ['Mondial',        ['SMX 125', 'SMX 250', 'HPS 125', 'HPS 300', 'HPS 400', 'HPS 700', 'MH 125', 'RD 125', 'MG 200', 'ZTK 250']],
            ['RKS',            ['Falcon 125', 'Falcon 200', 'RKS 125', 'RKS 250', 'MRX 250', 'X-Rider 125']],
            ['Kuba',           ['Cerro 200', 'Milano 125', 'Duello 250']],
            ['Ramzey',         ['Rally 125', 'Chopper 250', 'Sport 250', 'Vespo 125']],
            ['Kanuni',         ['Sport 100', 'Windy 125', 'Cruiser 125']],
            ['Volta',          ['VSM RS', 'VSX', 'VS4', 'VS3']],
            ['Roxor',          ['C1', 'C2', 'X1']],
            ['GTX',            ['GTX 125', 'GTX 250']],
            ['Falcon',         ['Falcon 125', 'Falcon 250']],
            ['Yuki',           ['Yuki 100', 'Yuki 125', 'Yuki 250']],
            ['Zongshen',       ['ZS 125', 'ZS 250', 'ZS 300']],
            ['Lifan',          ['KP 150', 'KP 200', 'KP 350', 'X-Pect 200']],
            ['GG Motors',      ['GG 125', 'GG 200']],
        ];
    }
}
