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
        Schema::table('trucks', function (Blueprint $table) {
            $table->string('fleeti_asset_id')->nullable()->after('maintenance_type')->index();
            $table->string('fleeti_gateway_id')->nullable()->after('fleeti_asset_id');
            $table->decimal('fleeti_last_kilometers', 15, 2)->nullable()->after('fleeti_gateway_id');
            $table->timestamp('fleeti_last_synced_at')->nullable()->after('fleeti_last_kilometers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            $table->dropColumn([
                'fleeti_asset_id',
                'fleeti_gateway_id',
                'fleeti_last_kilometers',
                'fleeti_last_synced_at',
            ]);
        });
    }
};
