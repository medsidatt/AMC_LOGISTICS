<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record consent for WhatsApp dispatch notifications. Presence of a timestamp
 * is the audit trail Meta requires; null means "not opted in" and the driver
 * is skipped at send-time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->timestamp('whatsapp_opt_in_at')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('whatsapp_opt_in_at');
        });
    }
};
