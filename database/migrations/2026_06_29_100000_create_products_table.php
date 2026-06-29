<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth for workshop / maintenance products & consumables
 * (the Product Catalog). NOT for transported material grades — those will get a
 * separate `material_grades` table later. No product name is ever hardcoded again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Lower-trimmed key for case-insensitive dedup (one product per real name).
            $table->string('name_key')->unique();
            $table->string('reference')->nullable();
            $table->string('category', 32)->nullable(); // piece | huile | autre (free, optional)
            $table->string('unit', 16)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
