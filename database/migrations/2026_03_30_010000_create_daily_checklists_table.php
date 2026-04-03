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
        Schema::create('daily_checklists', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Truck::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Driver::class)->constrained()->cascadeOnDelete();

            $table->date('checklist_date');

            // Daily inspection fields (mostly issue flags)
            $table->text('tire_condition')->nullable();
            $table->text('fuel_level')->nullable();
            $table->boolean('fuel_refill')->default(false);
            $table->text('oil_level')->nullable();
            $table->text('brakes')->nullable();
            $table->text('lights')->nullable();
            $table->text('general_condition_notes')->nullable();

            // SharePoint audit
            $table->string('sharepoint_item_id')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['truck_id', 'checklist_date'], 'daily_checklists_truck_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_checklists');
    }
};

