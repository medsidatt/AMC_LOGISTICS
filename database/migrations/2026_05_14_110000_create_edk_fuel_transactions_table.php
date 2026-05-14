<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('edk_fuel_transactions')) {
            Schema::create('edk_fuel_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('truck_id')->constrained('trucks')->cascadeOnDelete();
                $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
                $table->string('transaction_id', 64)->nullable()->index();
                $table->string('card_number', 32)->nullable();
                $table->string('holder_raw', 191)->nullable();
                $table->decimal('amount_fcfa', 14, 2);
                $table->decimal('litres', 12, 2);
                $table->decimal('price_per_litre', 8, 2);
                $table->timestamp('occurred_at')->index();
                $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['truck_id', 'occurred_at']);
            });
        }

        // Migrate existing rows from fuel_trackings where source='edk'
        if (Schema::hasTable('fuel_trackings') && Schema::hasColumn('fuel_trackings', 'source')) {
            $existing = DB::table('fuel_trackings')->where('source', 'edk')->get();
            foreach ($existing as $row) {
                DB::table('edk_fuel_transactions')->insert([
                    'truck_id' => $row->truck_id,
                    'driver_id' => null,
                    'transaction_id' => null,
                    'card_number' => null,
                    'holder_raw' => null,
                    'amount_fcfa' => 0,
                    'litres' => $row->litres,
                    'price_per_litre' => 0,
                    'occurred_at' => $row->created_at,
                    'imported_by' => null,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at ?? $row->created_at,
                ]);
            }
            DB::table('fuel_trackings')->where('source', 'edk')->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edk_fuel_transactions');
    }
};
