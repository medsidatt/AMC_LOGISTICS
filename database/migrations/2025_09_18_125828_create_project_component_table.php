<?php

use App\Models\Component;
use App\Models\Project;
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
        Schema::create('project_component', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Project::class)->constrained()->onDelete('cascade');
            $table->foreignIdFor(Component::class)->constrained()->onDelete('cascade');
            $table->boolean('active')->default(true);
            $table->boolean('is_its')->default(true);
            $table->boolean('is_cnss')->default(true);
            $table->boolean('is_cnam')->default(true);
            $table->boolean('is_base_salary')->default(false);
            $table->enum('nature', ['brut', 'net'])->default('brut');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_component');
    }
};
