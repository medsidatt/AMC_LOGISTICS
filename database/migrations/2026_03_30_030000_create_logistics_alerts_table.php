<?php

use App\Models\Driver;
use App\Models\Truck;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_alerts', function (Blueprint $table) {
            $table->id();

            $table->string('type', 50); // due_engine, missing_daily

            $table->foreignIdFor(Truck::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Driver::class)->nullable()->constrained()->nullOnDelete();

            $table->date('checklist_date')->nullable();

            $table->text('message');

            $table->timestamp('read_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
        });

        Schema::table('logistics_alerts', function (Blueprint $table) {
            $table->unique(['type', 'truck_id', 'checklist_date'], 'logistics_alerts_type_truck_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_alerts');
    }
};

