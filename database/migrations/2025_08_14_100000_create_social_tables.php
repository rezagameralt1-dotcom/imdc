<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
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

        Schema::create('safe_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('sealed')->default(false);
            $table->timestamps();
            $table->index(['owner_id', 'sealed']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('safe_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['safe_room_id', 'from_user_id']);
        });

        Schema::create('panic_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panic_codes');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('safe_rooms');
        Schema::dropIfExists('posts');
    }
};
