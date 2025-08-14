<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('posts')) {
            Schema::table('posts', function (Blueprint $table) {
                if (! $this->hasIndex($table, 'posts_published_at_index')) {
                    $table->index('published_at', 'posts_published_at_index');
                }
            });
        }

        if (Schema::hasTable('assets')) {
            Schema::table('assets', function (Blueprint $table) {
                if (Schema::hasColumn('assets', 'disk') && Schema::hasColumn('assets', 'path')) {
                    if (! $this->hasIndex($table, 'assets_disk_path_index')) {
                        $table->index(['disk', 'path'], 'assets_disk_path_index');
                    }
                }
            });
        }

        if (Schema::hasTable('admin_activity_logs')) {
            Schema::table('admin_activity_logs', function (Blueprint $table) {
                if (! $this->hasIndex($table, 'admin_activity_logs_created_at_index')) {
                    $table->index('created_at', 'admin_activity_logs_created_at_index');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('posts')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropIndex('posts_published_at_index');
            });
        }
        if (Schema::hasTable('assets')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropIndex('assets_disk_path_index');
            });
        }
        if (Schema::hasTable('admin_activity_logs')) {
            Schema::table('admin_activity_logs', function (Blueprint $table) {
                $table->dropIndex('admin_activity_logs_created_at_index');
            });
        }
    }

    private function hasIndex(Blueprint $table, string $indexName): bool
    {
        // Laravel doesn't expose an index-exists checker on Blueprint; use schema manager
        $connection = Schema::getConnection();
        $schema = $connection->getDoctrineSchemaManager();
        $platform = $schema->getDatabasePlatform();
        $schema->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

        $doctrineTable = $schema->listTableDetails($table->getTable());
        return $doctrineTable->hasIndex($indexName);
    }
};

