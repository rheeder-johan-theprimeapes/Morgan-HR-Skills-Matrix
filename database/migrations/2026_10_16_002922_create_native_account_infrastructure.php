<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->string('employee_number', 40)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('employee_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('login_id', 40)->nullable()->unique();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->boolean('is_active')->default(true);
            $table->boolean('can_administer_accounts')->default(false);
            $table->unsignedInteger('session_version')->default(1);
        });
        Schema::create('account_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
        Schema::create('account_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereNotNull('login_id')->exists() || DB::table('employees')->exists()) {
            throw new RuntimeException('Native accounts require a reviewed forward recovery; rollback would discard identity history.');
        }
        Schema::dropIfExists('account_audits');
        Schema::dropIfExists('account_access_tokens');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['employee_id']);
            $table->dropUnique(['employee_id']);
            $table->dropUnique(['login_id']);
            $table->dropColumn(['employee_id', 'login_id', 'is_active', 'can_administer_accounts', 'session_version']);
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
        Schema::dropIfExists('employees');
    }
};
