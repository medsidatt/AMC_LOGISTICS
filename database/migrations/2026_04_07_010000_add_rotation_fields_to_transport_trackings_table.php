<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_trackings', function (Blueprint $table) {
            $table->decimal('start_km', 15, 2)->nullable()->after('gap');
            $table->decimal('end_km', 15, 2)->nullable()->after('start_km');
            $table->boolean('is_validated')->default(false)->after('end_km');
            $table->timestamp('validated_at')->nullable()->after('is_validated');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete()->after('validated_at');

            $table->index(['truck_id', 'is_validated']);
        });
    }

    public function down(): void
    {
        Schema::table('transport_trackings', function (Blueprint $table) {
            $table->dropIndex(['truck_id', 'is_validated']);
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn(['start_km', 'end_km', 'is_validated', 'validated_at']);
        });
    }
};
