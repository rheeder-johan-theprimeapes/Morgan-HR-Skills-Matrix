<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_frameworks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('predecessor_id')->nullable()->constrained('scoring_frameworks')->restrictOnDelete();
            $table->string('name', 180);
            $table->boolean('illustrative')->default(true);
            $table->string('approval_reference', 1000)->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->json('definition');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_frameworks');
    }
};
