<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('skill_group_id')->constrained()->restrictOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('allows_na')->default(false);
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('skill_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('skill_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('ordinal');
            $table->string('label', 100);
            $table->text('guidance');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['skill_id', 'ordinal']);
        });
        Schema::create('department_skill', function (Blueprint $table): void {
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('skill_id')->constrained()->restrictOnDelete();
            $table->primary(['department_id', 'skill_id']);
        });
        Schema::create('job_role_skill', function (Blueprint $table): void {
            $table->foreignId('job_role_id')->constrained()->restrictOnDelete();
            $table->foreignId('skill_id')->constrained()->restrictOnDelete();
            $table->primary(['job_role_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_role_skill');
        Schema::dropIfExists('department_skill');
        Schema::dropIfExists('skill_levels');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('skill_groups');
    }
};
