<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Re-generate all references ordered by client_date (oldest first = AMC00001).
     */
    public function up(): void
    {
        $trackings = DB::table('transport_trackings')
            ->whereNull('deleted_at')
            ->orderBy('client_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $counter = 1;

        foreach ($trackings as $tracking) {
            $reference = 'AMC' . str_pad($counter, 5, '0', STR_PAD_LEFT);

            DB::table('transport_trackings')
                ->where('id', $tracking->id)
                ->update(['reference' => $reference]);

            $counter++;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed — references are permanent
    }
};
