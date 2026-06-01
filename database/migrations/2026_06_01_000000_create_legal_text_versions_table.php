<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hukuki metinlerin versiyonlu kaydı.
 *
 * Her hukuki metin (Hizmet Şartları, KVKK, Paylaşımlı Yolculuk vb.) bir
 * "key" ile tanımlanır ve değiştikçe yeni bir versiyon row'u eklenir;
 * eski versiyonun `superseded_at` alanı set edilir (silinmez!).
 *
 * Kullanıcı bir metni kabul ettiğinde `legal_consents` tablosunda
 * `text_version_id` ile bu satıra link verilir — böylece "hangi tarihteki
 * hangi metnin tam içeriği kabul edildi" sonsuza dek ispatlanabilir.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('legal_text_versions', function (Blueprint $table) {
            $table->id();

            // Metin kimliği (terms, kvkk, distance_sales, cookies, ride_sharing,
            // platform_notice, driver_registration, reservation_kvkk vs.)
            $table->string('key', 64)->index();

            // Versiyon etiketi (örn. v1.0-2026-06-01)
            $table->string('version', 64);

            // Kullanıcıya ne metin görünüyorsa onun tam içeriği (görsel HTML/text)
            // Mahkemede "hangi metin gösterildi" sorusu için kritik.
            $table->longText('content');

            // İçeriğin SHA-256 hash'i. Hızlı kıyaslama + bütünlük kontrolü.
            $table->char('sha256', 64);

            // Yayın bilgisi
            $table->timestamp('published_at')->useCurrent();
            $table->timestamp('superseded_at')->nullable()->comment('Yeni versiyon publish edildiğinde set edilir');

            // İnsan-okur açıklama
            $table->string('title')->nullable();
            $table->text('change_notes')->nullable()->comment('Bu versiyonda neyin değiştiğine dair admin notu');

            $table->timestamps();

            $table->unique(['key', 'version']);
            // Aktif versiyon hızlı erişim için
            $table->index(['key', 'superseded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_text_versions');
    }
};
