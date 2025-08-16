<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('code', 32)->unique();
                $t->string('title');
                $t->timestamps();
                $t->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
        if (! Schema::hasTable('vouchers')) {
            Schema::create('vouchers', function (Blueprint $t) {
                $t->id();
                $t->string('no')->unique();
                $t->timestamp('issued_at')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $t) {
                $t->id();
                $t->string('event');
                $t->unsignedBigInteger('user_id')->nullable();
                $t->jsonb('payload')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('accounts');
    }
};
