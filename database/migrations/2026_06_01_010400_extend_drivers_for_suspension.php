<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sürücü askıya alma altyapısı.
 *
 * Çağrı merkezi güvenlik olayı sırasında sürücüyü dondurabilir.
 * Sürücü askıdayken:
 *  - Yeni talep atanmaz
 *  - Mevcut talep iptal edilir
 *  - Panel'e giriş yapsa da "Askıya alındınız" ekranı görür
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->boolean('is_suspended')->default(false)->after('availability_status')->index();
            $table->timestamp('suspended_at')->nullable()->after('is_suspended');
            $table->string('suspended_reason', 255)->nullable()->after('suspended_at');
            $table->foreignId('suspended_by_user_id')->nullable()->after('suspended_reason')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('suspended_via_incident_id')->nullable()->after('suspended_by_user_id')
                ->constrained('security_incidents')->nullOnDelete();
            $table->timestamp('reinstated_at')->nullable()->after('suspended_via_incident_id');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by_user_id');
            $table->dropConstrainedForeignId('suspended_via_incident_id');
            $table->dropColumn([
                'is_suspended',
                'suspended_at',
                'suspended_reason',
                'reinstated_at',
            ]);
        });
    }
};
