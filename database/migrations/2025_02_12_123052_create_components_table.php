<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['brut', 'net'])->default('net');
            $table->boolean('is_displayed')->default(false);
            $table->boolean('is_summed')->default(true);
            $table->boolean('is_cnam')->default(true);
            $table->boolean('is_cnss')->default(true);
            $table->boolean('is_its')->default(true);
            $table->decimal('amount', 10, 0)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('components');
    }
};
