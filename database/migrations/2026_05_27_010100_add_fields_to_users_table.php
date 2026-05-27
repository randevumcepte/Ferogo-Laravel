<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->enum('type', ['admin', 'customer', 'driver'])->default('customer')->after('email');
            $table->string('phone', 20)->nullable()->after('type');
            $table->string('tc_no', 11)->nullable()->after('phone');
            $table->date('birth_date')->nullable()->after('tc_no');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('birth_date');
            $table->string('avatar')->nullable()->after('gender');
            $table->enum('status', ['active', 'suspended', 'pending'])->default('active')->after('avatar');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');

            $table->index('phone');
            $table->index('tc_no');
            $table->index(['tenant_id', 'type']);
        });

        // phone benzersizliği (NULL'lar serbest)
        Schema::table('users', function (Blueprint $table) {
            $table->unique(['tenant_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'phone']);
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'type']);
            $table->dropIndex(['phone']);
            $table->dropIndex(['tc_no']);
            $table->dropColumn([
                'tenant_id', 'type', 'phone', 'tc_no', 'birth_date',
                'gender', 'avatar', 'status', 'phone_verified_at',
            ]);
        });
    }
};
