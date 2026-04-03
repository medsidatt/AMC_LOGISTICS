<?php

use App\Models\Entity;
use App\Models\JobTitle\JobTitle;
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
            $table->foreignIdFor(JobTitle::class)->after('id')->nullable();
            $table->foreignIdFor(Entity::class)->after('id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['job_title_id']);
            $table->dropColumn('job_title_id');
            $table->dropForeign(['entity_id']);
            $table->dropColumn('entity_id');
        });
    }
};
