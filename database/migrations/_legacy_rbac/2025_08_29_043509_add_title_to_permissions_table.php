<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::connection('core')->hasColumn('permissions', 'title')) {
            Schema::connection('core')->table('permissions', function (Blueprint $table) {
                $table->string('title')->nullable()->after('name');
            });
        }
    }
    public function down(): void {
        if (Schema::connection('core')->hasColumn('permissions', 'title')) {
            Schema::connection('core')->table('permissions', function (Blueprint $table) {
                $table->dropColumn('title');
            });
        }
    }
};
