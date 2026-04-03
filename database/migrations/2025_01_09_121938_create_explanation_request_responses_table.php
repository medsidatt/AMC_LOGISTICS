<?php

use App\Models\Auth\User;
use App\Models\Employee\ExplanationRequest;
use App\Models\explinationRequest;
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
        Schema::create('explanation_request_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ExplanationRequest::class);
            $table->foreignIdFor(User::class, 'user_id')->nullable();
            $table->text('response');
            $table->string('file')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignIdFor(User::class, 'approved_by')->nullable();
            $table->foreignIdFor(User::class, 'rejected_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
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
        Schema::dropIfExists('explanation_request_responses');
    }
};
