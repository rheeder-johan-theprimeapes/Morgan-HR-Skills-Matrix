<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_benchmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_role_id')->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->text('company_context');
            $table->text('duties');
            $table->text('limitations');
            $table->date('source_date');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('basis', 10);
            $table->unsignedInteger('weekly_hours_hundredths')->nullable();
            $table->unsignedInteger('weeks_per_year_hundredths')->nullable();
            $table->string('source', 1000);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('supersedes_id')->nullable()->unique()->constrained('pay_benchmarks')->restrictOnDelete();
            $table->string('correction_reason', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_benchmarks');
    }
};
