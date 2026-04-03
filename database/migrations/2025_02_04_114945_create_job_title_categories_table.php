<?php

use App\Models\JobTitle\JobTitleClass;
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
        Schema::create('job_title_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(JobTitleClass::class)->nullable();
            $table->string('name');
            $table->string('description')->nullable();
            $table->userActions();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_title_categories');
    }
};
