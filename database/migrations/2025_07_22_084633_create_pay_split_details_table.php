<?php

use App\Models\Employee\Employee;
use App\Models\PaySplit;
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
        Schema::create('pay_split_details', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(PaySplit::class)
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Foreign key to the pay_splits table');
            $table->string('component')->comment('Component name');
            $table->decimal('qte', 10, 2)->default(1)->comment('Quantity of the component');
            $table->decimal('amount', 10, 2)->default(0)->comment('Amount of the component');
            $table->decimal('total_amount', 10, 2)->default(0)->comment('Total amount of the component');
            $table->enum('type', ['addition', 'deduction'])
                ->default('addition')
                ->comment('Type of the component: addition or deduction');
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete column');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pay_split_details');
    }
};
