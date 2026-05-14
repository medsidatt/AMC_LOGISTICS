<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('action', 32);
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('subject_label')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'audit_logs_user_created_idx');
            $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_idx');
            $table->index(['action', 'created_at'], 'audit_logs_action_created_idx');
            $table->index('created_at', 'audit_logs_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
