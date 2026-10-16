<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('development_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->string('origin_kind', 20);
            $table->foreignId('origin_skill_id')->nullable()->constrained('skills')->restrictOnDelete();
            $table->foreignId('origin_assessment_revision_id')->nullable()->constrained('assessment_revisions')->restrictOnDelete();
            $table->foreignId('origin_release_id')->nullable()->constrained('pdr_releases')->restrictOnDelete();
            $table->unsignedInteger('origin_objective_index')->nullable();
            $table->json('origin_snapshot');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('development_actions');
    }
};
