<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fleeti_daily_records')) {
            Schema::create('fleeti_daily_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('truck_id')->constrained('trucks')->cascadeOnDelete();
                $table->date('record_date');
                $table->decimal('kilometers', 12, 2)->default(0);
                $table->decimal('volume_initial', 10, 2)->default(0);
                $table->decimal('volume_final', 10, 2)->default(0);
                $table->decimal('consumed', 10, 2)->default(0);
                $table->decimal('consumed_per_100km', 8, 2)->nullable();
                $table->unsignedSmallInteger('refills_count')->default(0);
                $table->decimal('refills_volume', 10, 2)->default(0);
                $table->unsignedSmallInteger('drains_count')->default(0);
                $table->decimal('drains_volume', 10, 2)->default(0);
                $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['truck_id', 'record_date']);
                $table->index('record_date');
            });
        }

        // Migrate existing rows from fuel_trackings where source='fleeti_report'
        if (Schema::hasTable('fuel_trackings') && Schema::hasColumn('fuel_trackings', 'source')) {
            $existing = DB::table('fuel_trackings')->where('source', 'fleeti_report')->get();
            foreach ($existing as $row) {
                $date = $row->created_at ? substr($row->created_at, 0, 10) : null;
                if (! $date) {
                    continue;
                }
                $alreadyExists = DB::table('fleeti_daily_records')
                    ->where('truck_id', $row->truck_id)
                    ->where('record_date', $date)
                    ->exists();
                if ($alreadyExists) {
                    continue;
                }
                DB::table('fleeti_daily_records')->insert([
                    'truck_id' => $row->truck_id,
                    'record_date' => $date,
                    'kilometers' => $row->kilometers_at ?? 0,
                    'volume_initial' => 0,
                    'volume_final' => 0,
                    'consumed' => $row->litres,
                    'consumed_per_100km' => null,
                    'refills_count' => 0,
                    'refills_volume' => 0,
                    'drains_count' => 0,
                    'drains_volume' => 0,
                    'imported_by' => null,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at ?? $row->created_at,
                ]);
            }
            DB::table('fuel_trackings')->where('source', 'fleeti_report')->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fleeti_daily_records');
    }
};
