<?php

use App\Models\InspectionChecklist;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_checklist_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(InspectionChecklist::class)->constrained()->cascadeOnDelete();

            $table->string('category', 50);
            $table->boolean('flagged')->default(true);
            $table->enum('severity', ['minor', 'major', 'critical'])->default('minor');

            $table->text('issue_notes')->nullable();

            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['inspection_checklist_id', 'category'], 'inspection_issues_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_checklist_issues');
    }
};
