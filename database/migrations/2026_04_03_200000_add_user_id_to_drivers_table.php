<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        // Auto-link existing drivers to users by matching email
        DB::statement("
            UPDATE drivers d
            INNER JOIN users u ON u.email = d.email AND d.email IS NOT NULL AND d.email != ''
            SET d.user_id = u.id
            WHERE d.user_id IS NULL
        ");

        // Fallback: match by name for drivers without email
        DB::statement("
            UPDATE drivers d
            INNER JOIN users u ON u.name = d.name
            SET d.user_id = u.id
            WHERE d.user_id IS NULL AND (d.email IS NULL OR d.email = '')
        ");
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
