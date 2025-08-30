<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ستون guard_name اگر نباشد
        if (Schema::hasTable('roles') && !Schema::hasColumn('roles', 'guard_name')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('guard_name')->default('web')->after('name');
            });
        }
        if (Schema::hasTable('permissions') && !Schema::hasColumn('permissions', 'guard_name')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->string('guard_name')->default('web')->after('name');
            });
        }

        // role_has_permissions از permission_role
        if (!Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');
                $table->primary(['permission_id','role_id'], 'rhp_pk');
            });
            if (Schema::hasTable('permission_role')) {
                DB::statement("INSERT INTO role_has_permissions (permission_id, role_id)
                               SELECT permission_id, role_id FROM permission_role");
            }
        }

        // model_has_roles از role_user
        if (!Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->primary(['role_id','model_id','model_type'], 'mhr_pk');
            });
            if (Schema::hasTable('role_user')) {
                DB::statement("INSERT INTO model_has_roles (role_id, model_type, model_id)
                               SELECT role_id, 'App\\\\Models\\\\User', user_id FROM role_user");
            }
        }

        // model_has_permissions از permission_user
        if (!Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->primary(['permission_id','model_id','model_type'], 'mhp_pk');
            });
            if (Schema::hasTable('permission_user')) {
                DB::statement("INSERT INTO model_has_permissions (permission_id, model_type, model_id)
                               SELECT permission_id, 'App\\\\Models\\\\User', user_id FROM permission_user");
            }
        }

        // Sanctum: اگر نبود بساز (معمولاً الان داری)
        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // چیزی رو برنمی‌گردونیم.
    }
};
