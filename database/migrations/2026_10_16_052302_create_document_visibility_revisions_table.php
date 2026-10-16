<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_visibility_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('historical_document_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('visibility', 30);
            $table->boolean('content_review_confirmed');
            $table->text('reason');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['historical_document_id', 'revision'], 'document_visibility_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_visibility_revisions');
    }
};
