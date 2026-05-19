<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_demand_plans')) {
            return;
        }

        Schema::create('client_demand_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->date('week_start_date');
            $table->decimal('required_tons', 10, 2)->default(0);
            $table->unsignedSmallInteger('required_trucks')->nullable();
            $table->enum('product', ['0/3', '3/8', '8/16'])->nullable();
            $table->unsignedTinyInteger('priority')->default(3);
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['week_start_date', 'priority']);
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_demand_plans');
    }
};
