<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historical_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->date('document_date');
            $table->text('source');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_name', 180);
            $table->string('storage_path')->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->string('visibility', 30)->default('hr_only');
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->index(['employee_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_documents');
    }
};
