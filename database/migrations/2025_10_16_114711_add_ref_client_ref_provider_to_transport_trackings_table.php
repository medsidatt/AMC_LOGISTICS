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
        Schema::table('transport_trackings', function (Blueprint $table) {
            $table->string('ref_provider')->nullable()->after('reference');
            $table->string('ref_client')->nullable()->after('ref_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transport_trackings', function (Blueprint $table) {
            $table->dropColumn(['ref_client', 'ref_provider']);
        });
    }
};
