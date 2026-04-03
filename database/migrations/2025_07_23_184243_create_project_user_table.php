<?php

use App\Models\Auth\User;
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
        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Project::class)
                ->constrained()
                ->onDelete('cascade');
            $table->foreignIdFor(User::class)
                ->constrained()
                ->onDelete('cascade');
            $table->enum('role', ['admin', 'manager', 'viewer'])
                ->default('viewer')
                ->comment('Role of the user in the project');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_user');
    }
};
