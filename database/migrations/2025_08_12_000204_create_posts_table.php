<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('posts')) {
            Schema::create('posts', function (Blueprint $t) {
                $t->id();
                $t->foreignId('author_id')->constrained('users')->cascadeOnDelete();
                $t->string('title');
                $t->string('slug')->unique();
                $t->string('excerpt', 500)->nullable();
                $t->longText('body')->nullable();
                $t->enum('status', ['draft','published','archived'])->default('draft')->index();
                $t->timestamp('published_at')->nullable()->index();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};

