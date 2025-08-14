<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('post_tag')) {
            Schema::create('post_tag', function (Blueprint $t) {
                $t->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
                $t->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
                $t->primary(['post_id', 'tag_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('post_tag');
    }
};
