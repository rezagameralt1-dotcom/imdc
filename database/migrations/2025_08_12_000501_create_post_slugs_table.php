<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_slugs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('post_id');
            $t->string('slug')->unique();
            $t->timestamps();
            $t->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_slugs');
    }
};
