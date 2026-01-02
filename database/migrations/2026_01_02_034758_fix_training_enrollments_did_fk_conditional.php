<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('enrollments') && !Schema::hasColumn('enrollments', 'did_id')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->uuid('did_id')->nullable()->after('user_id');
            });
        }

        try {
            $fkName = 'pub_enrollments_did_id_foreign';
            $existsFk = DB::connection('core')->selectOne("""
                select 1
                from pg_constraint c
                join pg_class t on t.oid = c.conrelid
                join pg_namespace n on n.oid = t.relnamespace
                where n.nspname = 'pub'
                  and t.relname = 'enrollments'
                  and c.conname = ?
                limit 1
            """, [$fkName]);

            if ($existsFk) {
                Schema::table('enrollments', function (Blueprint $table) use ($fkName) {
                    $table->dropForeign($fkName);
                });
            }
        } catch (\Throwable $e) {
        }

        $didProfilesExists = DB::connection('core')->selectOne("""
            select 1
            from information_schema.tables
            where table_schema = 'pub' and table_name = 'did_profiles'
            limit 1
        """);

        if ($didProfilesExists) {
            $fkName = 'pub_enrollments_did_id_foreign';
            $existsFk = DB::connection('core')->selectOne("""
                select 1
                from pg_constraint c
                join pg_class t on t.oid = c.conrelid
                join pg_namespace n on n.oid = t.relnamespace
                where n.nspname = 'pub'
                  and t.relname = 'enrollments'
                  and c.conname = ?
                limit 1
            """, [$fkName]);

            if (!$existsFk) {
                Schema::table('enrollments', function (Blueprint $table) {
                    $table->foreign('did_id')->references('id')->on('did_profiles')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        try {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->dropForeign('pub_enrollments_did_id_foreign');
            });
        } catch (\Throwable $e) {}
    }
};
