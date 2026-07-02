<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fuel Import Validation layer — every EDK row is classified before it reaches the canonical
 * `edk_fuel_recharges` table. Valid rows are tagged with their import batch; every exception is
 * stored (never discarded) with the original CSV values + detected truck/card/driver + reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source', 16)->default('edk');
            $table->string('original_filename', 191)->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('exception_rows')->default(0);
            $table->json('category_counts')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('edk_import_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuel_import_batch_id')->constrained('fuel_import_batches')->cascadeOnDelete();
            $table->string('status', 20)->index();
            $table->string('reason', 191);
            // Original CSV values (never lost)
            $table->unsignedInteger('line_number')->nullable();
            $table->text('raw_line')->nullable();
            $table->string('transaction_id', 64)->nullable()->index();
            $table->string('card_number', 32)->nullable();
            $table->string('holder_raw', 191)->nullable();
            $table->decimal('amount_fcfa', 14, 2)->nullable();
            $table->decimal('estimated_litres', 12, 2)->nullable();
            $table->timestamp('occurred_at')->nullable();
            // What the validator detected (for the reviewer)
            $table->foreignId('detected_truck_id')->nullable()->constrained('trucks')->nullOnDelete();
            $table->foreignId('detected_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('edk_fuel_recharges', function (Blueprint $table) {
            $table->foreignId('fuel_import_batch_id')->nullable()->after('imported_by')
                ->constrained('fuel_import_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('edk_fuel_recharges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fuel_import_batch_id');
        });
        Schema::dropIfExists('edk_import_exceptions');
        Schema::dropIfExists('fuel_import_batches');
    }
};
