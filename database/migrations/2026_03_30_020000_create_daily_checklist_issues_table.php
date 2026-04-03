<?php

use App\Models\DailyChecklist;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_checklist_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(DailyChecklist::class)->constrained()->cascadeOnDelete();

            $table->string('category', 50);
            $table->boolean('flagged')->default(true);

            $table->text('issue_notes')->nullable();

            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['daily_checklist_id', 'category'], 'daily_checklist_issues_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_checklist_issues');
    }
};

