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
                
                // NOTE: did_id FK is handled in a separate migration (2026_01_07_100004)
                // to ensure it's only created if did_profiles exists
            } catch (\Exception $e) {
                // FK may not be supported, skip
            }
        });

        // After table creation, conditionally add did_id FK if did_profiles exists
        try {
            $didExistsPub = DB::connection('core')->selectOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'pub' AND table_name = 'did_profiles'
                ) as exists
            ");

            $didExistsPublic = DB::connection('core')->selectOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'public' AND table_name = 'did_profiles'
                ) as exists
            ");

            if (($didExistsPub && $didExistsPub->exists) || ($didExistsPublic && $didExistsPublic->exists)) {
                // Drop existing FK if it exists (safe to run multiple times)
                try {
                    DB::connection('core')->statement("
                        ALTER TABLE pub.enrollments 
                        DROP CONSTRAINT IF EXISTS pub_enrollments_did_id_foreign CASCADE
                    ");
                } catch (\Exception $e) {
                    // Ignore if FK doesn't exist
                }

                // Add FK
                try {
                    if ($didExistsPub && $didExistsPub->exists) {
                        DB::connection('core')->statement("
                            ALTER TABLE pub.enrollments 
                            ADD CONSTRAINT pub_enrollments_did_id_foreign 
                            FOREIGN KEY (did_id) REFERENCES pub.did_profiles(id) 
                            ON DELETE SET NULL
                        ");
                    } elseif ($didExistsPublic && $didExistsPublic->exists) {
                        DB::connection('core')->statement("
                            ALTER TABLE pub.enrollments 
                            ADD CONSTRAINT pub_enrollments_did_id_foreign 
                            FOREIGN KEY (did_id) REFERENCES did_profiles(id) 
                            ON DELETE SET NULL
                        ");
                    }
                } catch (\Exception $e) {
                    // FK may already exist or creation failed, skip
                }
            }
        } catch (\Exception $e) {
            // Ignore if check fails
        }
    }

    public function down(): void
    {
        Schema::connection('core')->dropIfExists('pub.enrollments');
    }
};
