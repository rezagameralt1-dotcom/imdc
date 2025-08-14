<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('posts') && !Schema::hasColumn('posts', 'content')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->text('content')->nullable()->after('summary');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('posts') && Schema::hasColumn('posts', 'content')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropColumn('content');
            });
        }
    }
};
