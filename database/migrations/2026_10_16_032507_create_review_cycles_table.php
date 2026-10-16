<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_cycles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 180);
            $table->foreignId('scoring_framework_id')->constrained()->restrictOnDelete();
            $table->date('opens_on');
            $table->date('closes_on');
            $table->boolean('requires_employee_input')->default(true);
            $table->string('template_approval_reference', 1000)->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_cycles');
    }
};
