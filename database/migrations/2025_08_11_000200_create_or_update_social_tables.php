<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // posts
        if (! Schema::hasTable('posts')) {
            Schema::create('posts', function (Blueprint $t) {
                $t->id();
                $t->string('title');
                $t->string('summary', 500)->nullable();
                $t->longText('content')->nullable();
                $t->unsignedBigInteger('user_id');
                $t->string('status', 32)->default('draft');
                $t->timestamps();
                $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        } else {
            if (! Schema::hasColumn('posts', 'user_id')) {
                Schema::table('posts', function (Blueprint $t) {
                    $t->unsignedBigInteger('user_id')->nullable();
                });
                Schema::table('posts', function (Blueprint $t) {
                    $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                });
            }
            if (! Schema::hasColumn('posts', 'status')) {
                Schema::table('posts', function (Blueprint $t) {
                    $t->string('status', 32)->default('draft');
                });
            }
            if (! Schema::hasColumn('posts', 'summary')) {
                Schema::table('posts', function (Blueprint $t) {
                    $t->string('summary', 500)->nullable();
                });
            }
            if (! Schema::hasColumn('posts', 'created_at')) {
                Schema::table('posts', function (Blueprint $t) {
                    $t->timestamps();
                });
            }
        }

        // messages
        if (! Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('sender_id');
                $t->unsignedBigInteger('receiver_id')->nullable();
                $t->unsignedBigInteger('safe_room_id')->nullable();
                $t->text('body');
                $t->timestamps();
                $t->foreign('sender_id')->references('id')->on('users')->cascadeOnDelete();
                $t->foreign('receiver_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        // safe_rooms
        if (! Schema::hasTable('safe_rooms')) {
            Schema::create('safe_rooms', function (Blueprint $t) {
                $t->id();
                $t->string('name', 120);
                $t->string('panic_code', 64)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('safe_rooms');
        // posts را عمداً حذف نمی‌کنیم
    }
};
