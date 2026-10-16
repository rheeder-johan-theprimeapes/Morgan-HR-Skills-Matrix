<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdr_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pdr_review_id')->constrained()->restrictOnDelete();
            $table->foreignId('pdr_submission_id')->constrained()->restrictOnDelete();
            $table->foreignId('pdr_decision_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->date('held_on');
            $table->text('summary');
            $table->text('development_message')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->text('recorder_reason')->nullable();
            $table->timestamps();
            $table->unique(['pdr_submission_id', 'revision']);
        });
        Schema::table('pdr_reviews', function (Blueprint $table): void {
            $table->foreignId('current_feedback_id')->nullable()->constrained('pdr_feedback')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pdr_reviews', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_feedback_id'));
        Schema::dropIfExists('pdr_feedback');
    }
};
