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
            $table->dropForeign(['transporter_id']);
            $table->dropColumn('transporter_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transport_trackings', function (Blueprint $table) {
            $table->unsignedBigInteger('transporter_id')->nullable();
            $table->foreign('transporter_id')->references('id')->on('transporters')->onDelete('cascade');
        });
    }
};
