<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // roles.guard_name
        if (Schema::hasTable('roles') && !Schema::hasColumn('roles', 'guard_name')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('guard_name')->default('web');
            });
            try { DB::statement("UPDATE roles SET guard_name = 'web' WHERE guard_name IS NULL"); } catch (\Throwable $e) {}
        }

        // permissions.guard_name
        if (Schema::hasTable('permissions') && !Schema::hasColumn('permissions', 'guard_name')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->string('guard_name')->default('web');
            });
            try { DB::statement("UPDATE permissions SET guard_name = 'web' WHERE guard_name IS NULL"); } catch (\Throwable $e) {}
        }

        // جداول استاندارد Spatie (اگر نبودند بساز)
        if (!Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');
                $table->primary(['permission_id','role_id']);
            });
        }
        if (!Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('model_id');
                $table->string('model_type');
                $table->index(['model_id','model_type'], 'model_has_roles_model_id_model_type_index');
                $table->primary(['role_id','model_id','model_type']);
            });
        }
        if (!Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('model_id');
                $table->string('model_type');
                $table->index(['model_id','model_type'], 'model_has_permissions_model_id_model_type_index');
                $table->primary(['permission_id','model_id','model_type']);
            });
        }

        // مهاجرت از جداول legacy اگر وجود دارند
        if (Schema::hasTable('permission_role')) {
            try {
                DB::insert("INSERT INTO role_has_permissions (permission_id, role_id)
                            SELECT permission_id, role_id FROM permission_role
                            ON CONFLICT DO NOTHING");
            } catch (\Throwable $e) {}
        }
        if (Schema::hasTable('role_user')) {
            try {
                DB::insert("INSERT INTO model_has_roles (role_id, model_id, model_type)
                            SELECT role_id, user_id, 'App\\Models\\User' FROM role_user
                            ON CONFLICT DO NOTHING");
            } catch (\Throwable $e) {}
        }
        if (Schema::hasTable('permission_user')) {
            try {
                DB::insert("INSERT INTO model_has_permissions (permission_id, model_id, model_type)
                            SELECT permission_id, user_id, 'App\\Models\\User' FROM permission_user
                            ON CONFLICT DO NOTHING");
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        // چیزی را حذف نمی‌کنیم تا داده‌ها امن بمانند
    }
};
