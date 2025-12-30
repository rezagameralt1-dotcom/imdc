<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection()->getName();

        if (! in_array($connection, ['core', 'pgsql'])) {
            return;
        }

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->unique(['role_id', 'user_id']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['permission_id', 'role_id']);
        });

        Schema::create('permission_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->unique(['permission_id', 'user_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->string('auditable_type');
            $table->string('auditable_id');
            $table->json('payload')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('trace_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $connection = Schema::getConnection()->getName();

        if (! in_array($connection, ['core', 'pgsql'])) {
            return;
        }

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};

