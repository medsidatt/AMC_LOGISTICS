<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('maintenance_type')->index();
            $table->foreignId('assigned_to_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by_id')->nullable()->after('assigned_to_id')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_by_id');
            $table->foreignId('approved_by_id')->nullable()->after('assigned_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_id');
            $table->string('electronic_signature_name', 120)->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropForeign(['assigned_to_id']);
            $table->dropForeign(['assigned_by_id']);
            $table->dropForeign(['approved_by_id']);
            $table->dropColumn([
                'status',
                'assigned_to_id',
                'assigned_by_id',
                'assigned_at',
                'approved_by_id',
                'approved_at',
                'electronic_signature_name',
            ]);
        });
    }
};
