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
        Schema::create('recaps', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->integer('jour')->default(0);
            $table->integer('nuit')->default(0);
            $table->integer('superviseurs_hse')->default(0);
            $table->integer('superviseurs_operations')->default(0);
            $table->integer('superviseurs_magasin')->default(0);
            $table->integer('assistant_qhse')->default(0);
            $table->integer('total')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recaps');
    }
};
