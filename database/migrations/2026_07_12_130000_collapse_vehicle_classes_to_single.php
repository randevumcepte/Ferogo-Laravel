<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Araç sınıflarını TEK TİP'e indirger (Martı TAG gibi) — SİLMEDEN.
 *
 * - Tam olarak BİR aktif sınıf bırakılır ("Standart"). 'easy' varsa o korunur
 *   (koddaki hardcoded 'easy' referansları kırılmasın), yoksa ilk sınıf tutulur.
 * - Diğer tüm sınıflar pasife çekilir (is_active=false) — satırlar/FK'ler durur.
 * - Pasif sınıfa bağlı ARAÇLAR aktif (Standart) sınıfa taşınır; NOT NULL FK korunur,
 *   dispatcher sınıf-eşleşmesi tetiklense bile sürücü dışlanmaz.
 *
 * Geçmiş rides/ride_requests DOKUNULMAZ. Geri alınabilir (down).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Aktif kalacak tek sınıf: 'easy' varsa o, yoksa ilk kayıt.
        $keep = DB::table('vehicle_classes')->where('slug', 'easy')->orderBy('id')->first()
            ?? DB::table('vehicle_classes')->orderBy('id')->first();

        if (! $keep) {
            return; // hiç sınıf yoksa dokunma
        }

        // Tutulan sınıfı "Standart" + tek aktif yap
        DB::table('vehicle_classes')->where('id', $keep->id)->update([
            'name'       => 'Standart',
            'is_active'  => true,
            'sort_order' => 0,
        ]);

        // Diğer tüm sınıflar pasif
        DB::table('vehicle_classes')->where('id', '!=', $keep->id)->update([
            'is_active' => false,
        ]);

        // Tüm araçları tek aktif sınıfa taşı (NOT NULL FK korunur)
        DB::table('vehicles')->where('vehicle_class_id', '!=', $keep->id)->update([
            'vehicle_class_id' => $keep->id,
        ]);
    }

    public function down(): void
    {
        // Sınıfları geri aç + adı geri al (araç taşımaları tek yönlü — geri alınmaz)
        DB::table('vehicle_classes')->update(['is_active' => true]);
        DB::table('vehicle_classes')->where('slug', 'easy')->update(['name' => 'Easy']);
    }
};
