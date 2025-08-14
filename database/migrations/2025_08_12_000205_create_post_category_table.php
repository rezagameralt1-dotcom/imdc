<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('post_category')) {
            Schema::create('post_category', function (Blueprint $t) {
                $t->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
                $t->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $t->primary(['post_id','category_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('post_category');
    }
};

