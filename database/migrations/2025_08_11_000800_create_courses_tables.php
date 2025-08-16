<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses')) {
            Schema::create('courses', function (Blueprint $t) {
                $t->id();
                $t->string('title');
                $t->text('description')->nullable();
                $t->unsignedBigInteger('teacher_id')->nullable();
                $t->timestamps();
                $t->foreign('teacher_id')->references('id')->on('users')->nullOnDelete();
            });
        }
        if (! Schema::hasTable('classes')) {
            Schema::create('classes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('course_id');
                $t->string('code')->unique();
                $t->timestamps();
                $t->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            });
        }
        if (! Schema::hasTable('enrollments')) {
            Schema::create('enrollments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('class_id');
                $t->unsignedBigInteger('user_id');
                $t->timestamps();
                $t->unique(['class_id', 'user_id']);
                $t->foreign('class_id')->references('id')->on('classes')->cascadeOnDelete();
                $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
        if (! Schema::hasTable('skill_nfts')) {
            Schema::create('skill_nfts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->unsignedBigInteger('course_id')->nullable();
                $t->string('token_id')->unique();
                $t->timestamps();
                $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $t->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_nfts');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('courses');
    }
};
