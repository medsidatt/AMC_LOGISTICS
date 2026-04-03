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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donor_id')->nullable()->constrained('donors')->nullOnDelete();
            $table->foreignId('beneficiary_id')->nullable()->constrained('beneficiaries')->nullOnDelete();
            $table->foreignId('bank_id')->constrained('banks')->cascadeOnDelete();
            $table->string('account_type')->nullable();
            $table->string('bank_code')->nullable();
            $table->string('branch_code')->nullable();
            $table->string('account_number')->nullable();
            $table->string('rib_key')->nullable();
            $table->string('iban')->nullable();
            $table->string('branch')->nullable();
            $table->string('account_name')->nullable();
            $table->string('type')->nullable();
            $table->string('key')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
