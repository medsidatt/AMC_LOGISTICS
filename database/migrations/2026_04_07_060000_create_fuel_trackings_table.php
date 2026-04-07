<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_id')->constrained()->cascadeOnDelete();
            $table->decimal('litres', 10, 2); // fuel level in litres
            $table->decimal('kilometers_at', 15, 2)->nullable(); // odometer at reading
            $table->string('source', 30)->default('fleeti'); // fleeti, checklist, manual
            $table->timestamps();

            $table->index(['truck_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_trackings');
    }
};
