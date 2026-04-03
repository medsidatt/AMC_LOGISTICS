<?php

use App\Models\Document;
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
        Schema::table('transport_trackings', function (Blueprint $table) {
            $table->string('commune_weight')->nullable();
            $table->date('commune_date')->nullable();
            $table->foreignIdFor(Document::class)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transport_trackings', function (Blueprint $table) {
            $table->dropColumn('commune_weight');
            $table->dropColumn('commune_date');
            $table->dropForeign(['document_id']);
        });
    }
};
