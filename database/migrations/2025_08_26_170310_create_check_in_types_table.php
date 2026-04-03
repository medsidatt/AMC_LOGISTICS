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
        Schema::create('check_in_types', function (Blueprint $table) {
            $table->id();
            // type
            $table->string('type')->unique();
            //label
            $table->string('label');
            //nature
            $table->string('nature')->nullable();
            //code
            $table->string('code')->nullable();
            //summable
            $table->boolean('summable')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_in_types');
    }
};
