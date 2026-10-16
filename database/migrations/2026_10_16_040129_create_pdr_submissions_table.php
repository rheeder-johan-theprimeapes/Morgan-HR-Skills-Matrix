<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdr_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pdr_review_id')->constrained()->restrictOnDelete();
            $table->foreignId('pdr_draft_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('submission_number');
            $table->foreignId('previous_submission_id')->nullable()->constrained('pdr_submissions')->restrictOnDelete();
            $table->unsignedInteger('schema_version')->default(1);
            $table->json('snapshot');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['pdr_review_id', 'submission_number']);
        });
        Schema::table('pdr_reviews', function (Blueprint $table): void {
            $table->foreignId('current_submission_id')->nullable()->constrained('pdr_submissions')->restrictOnDelete();
        });
        Schema::table('pdr_drafts', function (Blueprint $table): void {
            $table->foreignId('previous_submission_id')->nullable()->constrained('pdr_submissions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pdr_drafts', fn (Blueprint $table) => $table->dropConstrainedForeignId('previous_submission_id'));
        Schema::table('pdr_reviews', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_submission_id'));
        Schema::dropIfExists('pdr_submissions');
    }
};
