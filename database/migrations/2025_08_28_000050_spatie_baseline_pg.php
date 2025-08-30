<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ---- roles.guard_name ----
        if (Schema::hasTable('roles') && !Schema::hasColumn('roles', 'guard_name')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('guard_name')->default('web');
            });
            try { DB::statement("UPDATE roles SET guard_name = 'web' WHERE guard_name IS NULL"); } catch (\Throwable $e) {}
        }
        if (Schema::hasTable('roles')) {
            try { DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS roles_name_guard_unique ON roles (name, guard_name)"); } catch (\Throwable $e) {}
        }

        // ---- permissions ----
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name')->default('web');
                $table->timestamps();
            });
            try { DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS permissions_name_guard_unique ON permissions (name, guard_name)"); } catch (\Throwable $e) {}
        } else {
            if (!Schema::hasColumn('permissions', 'guard_name')) {
                Schema::table('permissions', function (Blueprint $table) {
                    $table->string('guard_name')->default('web');
                });
            }
            try { DB::statement("UPDATE permissions SET guard_name = 'web' WHERE guard_name IS NULL"); } catch (\Throwable $e) {}
            try { DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS permissions_name_guard_unique ON permissions (name, guard_name)"); } catch (\Throwable $e) {}
        }

        // ---- role_has_permissions (استاندارد Spatie) ----
        if (!Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');
                $table->primary(['permission_id','role_id']);
            });
        }

        // ---- model_has_roles ----
        if (!Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('model_id');
                $table->string('model_type');
                $table->index(['model_id','model_type'], 'model_has_roles_model_index');
                $table->primary(['role_id','model_id','model_type']);
            });
        }

        // ---- model_has_permissions ----
        if (!Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('model_id');
                $table->string('model_type');
                $table->index(['model_id','model_type'], 'model_has_permissions_model_index');
                $table->primary(['permission_id','model_id','model_type']);
            });
        }

        // ---- FKها (با try تا اگر موجود بود، خطا نده) ----
        try {
            DB::statement("ALTER TABLE role_has_permissions
                ADD CONSTRAINT role_has_permissions_permission_id_fkey
                FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE");
        } catch (\Throwable $e) {}
        try {
            DB::statement("ALTER TABLE role_has_permissions
                ADD CONSTRAINT role_has_permissions_role_id_fkey
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE");
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        // برگشت امن (هیچی) — برای محیط dev نیازی به tear-down نداریم.
    }
};
