<?php

use App\Models\Auth\User;
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
        Schema::create('explanation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class, 'user_id')->nullable();
            $table->string('title');
            $table->text('description');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('response_status', ['pending', 'answered'])->default('pending');
            $table->foreignIdFor(User::class, 'approved_by')->nullable();
            $table->foreignIdFor(User::class, 'rejected_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
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
        Schema::dropIfExists('explanation_requests');
    }
};
