<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdr_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pdr_review_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('draft_number')->default(1);
            $table->unsignedInteger('revision')->default(1);
            $table->json('employee_section');
            $table->json('manager_section');
            $table->json('pay_proposal')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('last_saved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['pdr_review_id', 'draft_number']);
        });
        Schema::table('pdr_reviews', function (Blueprint $table): void {
            $table->foreign('current_draft_id')->references('id')->on('pdr_drafts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pdr_reviews', function (Blueprint $table): void {
            $table->dropForeign(['current_draft_id']);
        });
        Schema::dropIfExists('pdr_drafts');
    }
};
