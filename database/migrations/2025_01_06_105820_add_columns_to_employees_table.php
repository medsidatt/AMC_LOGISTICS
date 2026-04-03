<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('father_name')->nullable()->after('last_name');
            $table->string('mother_name')->nullable()->after('father_name');
            $table->string('nationality')->nullable()->after('mother_name');
            $table->enum('gender', ['male', 'female'])->after('nationality')->nullable();
            $table->string('nni')->nullable()->unique()->after('address');
            $table->date('birth_date')->nullable()->after('nni');
            $table->date('hire_date')->nullable()->after('birth_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {

        });
    }
};
