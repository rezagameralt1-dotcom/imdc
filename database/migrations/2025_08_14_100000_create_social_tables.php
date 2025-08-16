<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // posts
        if (!Schema::hasTable('posts')) {
            Schema::create('posts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('title')->nullable();
                $table->text('body');
                $table->json('media')->nullable();
                $table->boolean('is_public')->default(true);
                $table->timestamps();
                $table->index(['user_id', 'is_public']);
            });
        }

        // safe_rooms
        if (!Schema::hasTable('safe_rooms')) {
            Schema::create('safe_rooms', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('sealed')->default(false);
                $table->timestamps();
                $table->index(['owner_id', 'sealed']);
            });
        }

        // messages
        if (!Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('safe_room_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('receiver_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('body');
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index(['safe_room_id', 'sender_id']);
            });
        }

        // panic_codes
        if (!Schema::hasTable('panic_codes')) {
            Schema::create('panic_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('code_hash');
                $table->timestamp('last_triggered_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('panic_codes');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('safe_rooms');
        Schema::dropIfExists('posts');
    }
};
