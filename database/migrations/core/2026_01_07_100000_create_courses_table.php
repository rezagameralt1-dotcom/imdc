<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create pub schema if it doesn't exist
        DB::connection('core')->statement('CREATE SCHEMA IF NOT EXISTS pub');

        // Check if table already exists (idempotent)
        if (Schema::connection('core')->hasTable('pub.courses')) {
            return;
        }

        Schema::connection('core')->create('pub.courses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('level', 32)->nullable();
            $table->string('language', 16)->nullable();
            $table->uuid('teacher_user_id')->comment('FK to pub.users.id (if exists)');
            $table->string('status', 16)->default('draft')->comment('draft, published, archived');
            $table->timestamps();

            $table->index(['status']);
            $table->index(['teacher_user_id']);
            $table->index(['level']);

            // Foreign key to pub.users if it exists (optional)
            try {
                // Check if pub.users exists before creating FK
                $usersExists = DB::connection('core')->selectOne("
                    SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE table_schema = 'pub' AND table_name = 'users'
                    ) as exists
                ");
                if ($usersExists && $usersExists->exists) {
                    $table->foreign('teacher_user_id')->references('id')->on('pub.users')->onDelete('restrict');
                }
            } catch (\Exception $e) {
                // FK may not be supported, skip
            }
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('pub.courses');
    }
};
