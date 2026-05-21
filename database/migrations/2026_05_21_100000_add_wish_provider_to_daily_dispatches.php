<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional "wish provider" on a daily dispatch — a soft preference the
 * dispatcher records at programming time so the field supervisor knows the
 * intended destination quarry/client when composing the truck fleet on the
 * day. Not a commitment, not sent in the WhatsApp notification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_dispatches', function (Blueprint $table) {
            $table->foreignId('wish_provider_id')
                ->nullable()
                ->after('truck_id')
                ->constrained('providers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_dispatches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wish_provider_id');
        });
    }
};
