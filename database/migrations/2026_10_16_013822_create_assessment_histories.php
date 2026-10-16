<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('skill_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->unsignedInteger('revision')->default(0);
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'skill_id', 'kind']);
        });
        Schema::create('assessment_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->date('assessed_on');
            $table->string('state', 20);
            $table->foreignId('skill_level_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->text('evidence_notes')->nullable();
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['assessment_id', 'revision']);
        });
        Schema::table('assessments', function (Blueprint $table): void {
            $table->foreign('current_revision_id')->references('id')->on('assessment_revisions')->restrictOnDelete();
        });
        Schema::create('evidence_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_revision_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_name', 180);
            $table->string('storage_path')->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('byte_size');
            $table->string('sha256', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_attachments');
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropForeign(['current_revision_id']);
        });
        Schema::dropIfExists('assessment_revisions');
        Schema::dropIfExists('assessments');
    }
};
