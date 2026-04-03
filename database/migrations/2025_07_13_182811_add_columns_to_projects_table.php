
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
        Schema::table('projects', function (Blueprint $table) {
            $table->string('matricule_cnss')->nullable()->after('name');
            $table->string('matricule_cnam')->nullable()->after('matricule_cnss');
            $table->string('bp')->nullable()->after('matricule_cnam');
            $table->string('address')->nullable()->after('bp');
            $table->string('phone')->nullable()->after('address');
            $table->string('email')->nullable()->after('phone');
            $table->string('cnam_code')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['matricule_cnss', 'matricule_cnam', 'bp', 'address', 'phone', 'email', 'cnam_code']);
        });
    }
};
