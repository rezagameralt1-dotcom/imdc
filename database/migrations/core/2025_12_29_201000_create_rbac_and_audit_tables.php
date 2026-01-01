<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skip if tables already exist
        if (Schema::connection('core')->hasTable('audit_logs')) {
            return;
        }

        Schema::connection('core')->create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::connection('core')->create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::connection('core')->create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::connection('core')->create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::connection('core')->create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        });

        Schema::connection('core')->create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 128);
            $table->string('auditable_type', 255);
            $table->string('auditable_id', 36);
            $table->json('payload')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('trace_id', 36)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('audit_logs');
        Schema::connection('core')->dropIfExists('role_has_permissions');
        Schema::connection('core')->dropIfExists('model_has_permissions');
        Schema::connection('core')->dropIfExists('model_has_roles');
        Schema::connection('core')->dropIfExists('permissions');
        Schema::connection('core')->dropIfExists('roles');
    }
};

