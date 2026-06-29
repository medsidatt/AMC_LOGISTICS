<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link maintenance line items to the Product Catalog. The free-text `designation`
 * column is KEPT (resolved product name for PDF/history); `product_id` becomes the
 * source of truth for which product was used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('designation')->constrained('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
