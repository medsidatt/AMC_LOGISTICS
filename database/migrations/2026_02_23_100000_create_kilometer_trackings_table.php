<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kilometer_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_id')->constrained()->onDelete('cascade');
            $table->decimal('kilometers', 10, 2); // Assuming kilometers can have decimals
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kilometer_trackings');
    }
};
