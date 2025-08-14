<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('assets')) {
            Schema::create('assets', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->string('disk')->default('public');
                $t->string('path');
                $t->string('original_name')->nullable();
                $t->string('mime', 100)->nullable();
                $t->unsignedBigInteger('size')->default(0);
                $t->unsignedInteger('width')->nullable();
                $t->unsignedInteger('height')->nullable();
                $t->string('alt')->nullable();
                $t->timestamps();
                $t->index(['mime', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};

