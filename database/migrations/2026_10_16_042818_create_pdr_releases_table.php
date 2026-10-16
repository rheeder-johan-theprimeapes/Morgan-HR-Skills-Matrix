<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdr_releases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pdr_review_id')->constrained()->restrictOnDelete();
            $table->foreignId('pdr_submission_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('pdr_feedback_id')->constrained('pdr_feedback')->restrictOnDelete();
            $table->json('summary');
            $table->foreignId('released_by')->constrained('users')->restrictOnDelete();
            $table->text('recorder_reason')->nullable();
            $table->timestamps();
        });
        Schema::table('pdr_reviews', function (Blueprint $table): void {
            $table->foreignId('current_release_id')->nullable()->constrained('pdr_releases')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pdr_reviews', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_release_id'));
        Schema::dropIfExists('pdr_releases');
    }
};
