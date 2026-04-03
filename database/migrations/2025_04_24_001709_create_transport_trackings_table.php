<?php

use App\Models\Driver;
use App\Models\Provider;
use App\Models\Transporter;
use App\Models\Truck;
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
        Schema::create('transport_trackings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('provider_date')->nullable();
            $table->date('client_date')->nullable();
            $table->text('client_file')->nullable();
            $table->text('provider_file')->nullable();
            $table->foreignIdFor(Transporter::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Truck::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Driver::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Provider::class)->nullable()->constrained()->cascadeOnDelete();
            $table->enum('product', ['0/3', '3/8', '8/16'])->nullable();
            $table->decimal('provider_gross_weight', 10, 2)->nullable();
            $table->decimal('provider_net_weight', 10, 2)->nullable();
            $table->decimal('provider_tare_weight', 10, 2)->nullable();
            $table->decimal('client_gross_weight', 10, 2)->nullable();
            $table->decimal('client_net_weight', 10, 2)->nullable();
            $table->decimal('client_tare_weight', 10, 2)->nullable();
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
        Schema::dropIfExists('transport_trackings');
    }
};
