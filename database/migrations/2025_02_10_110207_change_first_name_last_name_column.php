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
        Schema::table('employees', function (Blueprint $table) {
            $table->renameColumn('first_name', 'name');
            $table->dropColumn('last_name');
            $table->string('email')->nullable()->change();
            $table->date('release_date')->nullable()->after('hire_date');
            $table->string('badge_cnss')->nullable()->after('release_date');
            $table->string('badge_cnam')->nullable()->after('badge_cnss');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->renameColumn('name', 'first_name');
            $table->string('last_name');
            $table->string('email')->nullable(false)->change();
            $table->dropColumn('release_date');
        });
    }
};
