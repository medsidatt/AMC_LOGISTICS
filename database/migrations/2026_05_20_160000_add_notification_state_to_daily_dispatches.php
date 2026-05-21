<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Track per-dispatch WhatsApp notification state so the planning UI can show
 * "Notifié à HH:mm", "Échec — raison", "Pas de téléphone", etc., and so Meta
 * webhook updates have a column to write to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_dispatches', function (Blueprint $table) {
            $table->string('notification_status', 16)
                ->default('pending')
                ->after('notified_at');
            $table->text('notification_error')->nullable()->after('notification_status');
            $table->string('whatsapp_message_id', 80)->nullable()->after('notification_error');
            $table->index('whatsapp_message_id');
        });

        // Rows that pre-date the WhatsApp wiring should not appear as "pending"
        // forever — they will never be retried. Mark them as historically-skipped.
        DB::table('daily_dispatches')
            ->whereNull('notification_error')
            ->update([
                'notification_status' => 'skipped',
                'notification_error' => 'pré-existant — non envoyé',
            ]);
    }

    public function down(): void
    {
        Schema::table('daily_dispatches', function (Blueprint $table) {
            $table->dropIndex(['whatsapp_message_id']);
            $table->dropColumn(['notification_status', 'notification_error', 'whatsapp_message_id']);
        });
    }
};
