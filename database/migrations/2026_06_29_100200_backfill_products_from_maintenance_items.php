<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Migrate historical free-text maintenance part names into the Product Catalog.
 * Idempotent + safe to rerun: products are created only when absent (insertOrIgnore
 * on the unique name_key), and only line items still missing a product_id are linked.
 * The original `designation` text is preserved on every row (never deleted).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();

        // 1. Distinct, trimmed, case-insensitively-deduped designations → Product rows.
        $names = DB::table('maintenance_items')
            ->whereNotNull('designation')
            ->where('designation', '!=', '')
            ->pluck('designation')
            ->map(fn ($d) => trim((string) $d))
            ->filter(fn ($d) => $d !== '')
            ->unique(fn ($d) => mb_strtolower($d))
            ->values();

        if ($names->isNotEmpty()) {
            $rows = $names->map(fn ($name) => [
                'name' => $name,
                'name_key' => mb_strtolower($name),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('products')->insertOrIgnore($chunk);
            }
        }

        // 2. Link line items (only those still unlinked) to their product by name_key.
        DB::table('maintenance_items')
            ->whereNull('product_id')
            ->whereNotNull('designation')
            ->where('designation', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($items) {
                foreach ($items as $item) {
                    $key = mb_strtolower(trim((string) $item->designation));
                    if ($key === '') {
                        continue;
                    }
                    $productId = DB::table('products')->where('name_key', $key)->value('id');
                    if ($productId) {
                        DB::table('maintenance_items')->where('id', $item->id)->update(['product_id' => $productId]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Unlink only; keep the catalog + the preserved designation text.
        DB::table('maintenance_items')->update(['product_id' => null]);
    }
};
