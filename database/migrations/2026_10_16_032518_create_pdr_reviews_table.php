<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdr_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('manager_user_id')->constrained('users')->restrictOnDelete();
            $table->string('section_profile', 30);
            $table->json('section_template');
            $table->string('state', 30)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('current_draft_id')->nullable();
            $table->foreignId('allocated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['review_cycle_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdr_reviews');
    }
};
