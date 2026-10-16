<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('development_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('development_action_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('change_type', 20);
            $table->string('title', 180);
            $table->text('description');
            $table->date('starts_on');
            $table->date('due_on');
            $table->string('resource_label', 180)->nullable();
            $table->text('resource_url')->nullable();
            $table->string('state', 20);
            $table->text('notes')->nullable();
            $table->date('completed_on')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('original_name', 180)->nullable();
            $table->string('storage_path')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->timestamps();
            $table->unique(['development_action_id', 'revision']);
        });
        Schema::table('development_actions', function (Blueprint $table): void {
            $table->foreignId('current_revision_id')->nullable()->constrained('development_revisions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('development_actions', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_revision_id'));
        Schema::dropIfExists('development_revisions');
    }
};
