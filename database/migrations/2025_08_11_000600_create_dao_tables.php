<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('proposals')) {
            Schema::create('proposals', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('creator_id');
                $t->string('title');
                $t->text('body')->nullable();
                $t->timestamp('starts_at')->nullable();
                $t->timestamp('ends_at')->nullable();
                $t->timestamps();
                $t->foreign('creator_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
        if (! Schema::hasTable('votes')) {
            Schema::create('votes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('proposal_id');
                $t->unsignedBigInteger('user_id');
                $t->boolean('value'); // yes/no
                $t->timestamps();
                $t->unique(['proposal_id', 'user_id']);
                $t->foreign('proposal_id')->references('id')->on('proposals')->cascadeOnDelete();
                $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
        Schema::dropIfExists('proposals');
    }
};
