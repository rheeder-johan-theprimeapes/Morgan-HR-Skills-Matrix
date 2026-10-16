<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('metric', 40);
            $table->string('unit', 20);
            $table->unsignedBigInteger('value_hundredths')->nullable();
            $table->boolean('is_interpretation');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('source', 1000);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('supersedes_id')->nullable()->unique()->constrained('operational_records')->restrictOnDelete();
            $table->string('correction_reason', 1000)->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'sequence']);
            $table->index(['employee_id', 'metric', 'period_start', 'period_end'], 'operational_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_records');
    }
};
