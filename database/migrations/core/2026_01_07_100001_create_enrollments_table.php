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
        if (Schema::connection('core')->hasTable('pub.enrollments')) {
            return;
        }

        Schema::connection('core')->create('pub.enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('course_id')->comment('FK to pub.courses.id');
            $table->uuid('user_id')->comment('FK to pub.users.id (if exists)');
            $table->uuid('did_id')->nullable()->comment('FK to pub.did_profiles.id if exists');
            $table->string('status', 16)->default('enrolled')->comment('enrolled, completed, cancelled');
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Unique constraint: one enrollment per user per course (idempotency-friendly)
            $table->unique(['course_id', 'user_id']);

            $table->index(['course_id']);
            $table->index(['user_id']);
            $table->index(['did_id']);
            $table->index(['status']);

            // Foreign keys
            try {
                $table->foreign('course_id')->references('id')->on('pub.courses')->onDelete('cascade');
                
                // Check if pub.users exists
                $usersExists = DB::connection('core')->selectOne("
                    SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE table_schema = 'pub' AND table_name = 'users'
                    ) as exists
                ");
                if ($usersExists && $usersExists->exists) {
                    $table->foreign('user_id')->references('id')->on('pub.users')->onDelete('restrict');
                }
                
                // Check if did_profiles exists in pub schema or default
                $didExists = DB::connection('core')->selectOne("
                    SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE (table_schema = 'pub' AND table_name = 'did_profiles')
                        OR (table_schema = 'public' AND table_name = 'did_profiles')
                    ) as exists
                ");
                if ($didExists && $didExists->exists) {
                    // Try pub.did_profiles first, then public.did_profiles
                    try {
                        $table->foreign('did_id')->references('id')->on('pub.did_profiles')->onDelete('set null');
                    } catch (\Exception $e) {
                        try {
                            $table->foreign('did_id')->references('id')->on('did_profiles')->onDelete('set null');
                        } catch (\Exception $e2) {
                            // Skip FK if both fail
                        }
                    }
                }
            } catch (\Exception $e) {
                // FK may not be supported, skip
            }
        });
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('pub.enrollments');
    }
};
