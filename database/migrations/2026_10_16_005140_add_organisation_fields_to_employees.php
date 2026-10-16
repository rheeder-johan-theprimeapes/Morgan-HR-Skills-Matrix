<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('job_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('employees', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('job_role_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->restrictOnDelete();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('can_manage_directory')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('can_manage_directory');
        });
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('job_role_id');
            $table->dropConstrainedForeignId('department_id');
        });
        Schema::dropIfExists('job_roles');
        Schema::dropIfExists('departments');
    }
};
