<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bildirim kampanyaları — admin panelden yazılıp gönderilen toplu push/duyuru.
 *
 * Duyuru, indirim/kampanya, bilgilendirme ve uygulama-içi popup mesajları burada
 * tutulur. Hedef kitle (target JSON) + zamanlama (scheduled_at) + gönderim istatistiği.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->nullable()->unique();

            // ─── İçerik ───
            $table->string('title');
            $table->text('body');
            $table->string('image_url')->nullable();          // opsiyonel görsel (push + popup)
            $table->string('deep_link')->nullable();          // uygulama içi yönlendirme (ör: /campaigns/x)
            $table->string('type', 30)->default('announcement'); // announcement | promo | info
            $table->boolean('show_as_popup')->default(false); // uygulama açılışında modal göster

            // ─── Hedefleme ───
            // audience: all | customers | drivers
            $table->string('audience', 20)->default('all');
            // target: {city_id, women_only, active_package, trust_tiers[], phones[], user_ids[], min_completed}
            $table->json('target')->nullable();

            // ─── Zamanlama ───
            // status: draft | scheduled | sending | sent | cancelled
            $table->string('status', 20)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            // ─── İstatistik ───
            $table->unsignedInteger('recipients_count')->default(0); // hedeflenen kullanıcı
            $table->unsignedInteger('sent_count')->default(0);       // push başarıyla gönderilen
            $table->unsignedInteger('failed_count')->default(0);     // push başarısız
            $table->unsignedInteger('opened_count')->default(0);     // bildirimi açan (inbox okundu)

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_campaigns');
    }
};
