<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            // Kadın sürücü güvenliği: "sadece kadın yolcu al" tercihi.
            // Yalnızca kadın sürücüler için anlamlı (UI tarafında öyle kısıtlanır).
            $table->boolean('women_passengers_only')->default(false)->after('availability_status');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('women_passengers_only');
        });
    }
};
