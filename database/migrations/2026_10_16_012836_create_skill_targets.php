<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('skill_id')->constrained()->restrictOnDelete();
            $table->foreignId('skill_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('job_role_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['skill_id', 'department_id']);
            $table->unique(['skill_id', 'job_role_id']);
            $table->unique(['skill_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_targets');
    }
};
