<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdr_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pdr_review_id')->constrained()->restrictOnDelete();
            $table->foreignId('pdr_submission_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('head_version');
            $table->string('action', 40);
            $table->text('reason');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['pdr_review_id', 'head_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdr_transitions');
    }
};
