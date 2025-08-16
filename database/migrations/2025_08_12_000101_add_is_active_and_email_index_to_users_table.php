<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_admin');
            }
            // اطمینان از وجود ایندکس ایمیل (برای سرچ/لیست)
            try {
                $table->index('email');
            } catch (\Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropColumn('is_active');
            }
            try {
                $table->dropIndex(['email']);
            } catch (\Throwable $e) {
            }
        });
    }
};
