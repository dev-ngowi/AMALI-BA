<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('fullname');
                $table->string('username')->unique();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('phone_verified_at')->nullable();
                $table->string('password');
                $table->integer('pin')->nullable();
                $table->string('remember_token')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('username');
            });
        } else {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'fullname')) {
                    $table->string('fullname')->after('id');
                }
                if (!Schema::hasColumn('users', 'username')) {
                    $table->string('username')->unique()->after('fullname');
                    $table->index('username');
                }
                if (!Schema::hasColumn('users', 'email')) {
                    $table->string('email')->nullable()->after('username');
                }
                if (!Schema::hasColumn('users', 'phone')) {
                    $table->string('phone')->nullable()->after('email');
                }
                if (!Schema::hasColumn('users', 'email_verified_at')) {
                    $table->timestamp('email_verified_at')->nullable()->after('phone');
                }
                if (!Schema::hasColumn('users', 'phone_verified_at')) {
                    $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
                }
                if (!Schema::hasColumn('users', 'pin')) {
                    $table->integer('pin')->nullable()->after('password');
                }
                if (!Schema::hasColumn('users', 'remember_token')) {
                    $table->string('remember_token')->nullable()->after('pin');
                }
            });
        }

        // Password_reset_tokens table (create if not exist)
        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email', 191)->primary(); // Single email column as primary key
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        // Sessions table (create if not exist)
        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};