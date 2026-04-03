<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('matricule_cnss')->nullable()->after('slug');
            $table->string('matricule_cnam')->nullable()->after('matricule_cnss');
            $table->string('nif')->nullable()->after('matricule_cnam');
            $table->string('rc')->nullable()->after('nif');
            $table->string('address')->nullable()->after('rc');
            $table->string('phone')->nullable()->after('address');
            $table->string('email')->nullable()->after('phone');
            $table->string('website')->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('website');
            $table->string('logo')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn(['badge_cnss', 'badge_cnam', 'nif', 'rc', 'address', 'phone', 'email', 'website', 'is_active', 'logo']);
        });
    }
};
