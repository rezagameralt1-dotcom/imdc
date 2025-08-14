<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wallets')) {
            Schema::create('wallets', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->string('address')->unique();
                $t->string('provider')->nullable(); // e.g., 'internal'
                $t->timestamps();
                $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
        if (! Schema::hasTable('nft_transfers')) {
            Schema::create('nft_transfers', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('from_wallet_id')->nullable();
                $t->unsignedBigInteger('to_wallet_id')->nullable();
                $t->string('token_id');
                $t->string('contract')->nullable();
                $t->timestamp('transferred_at')->nullable();
                $t->timestamps();
                $t->foreign('from_wallet_id')->references('id')->on('wallets')->nullOnDelete();
                $t->foreign('to_wallet_id')->references('id')->on('wallets')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nft_transfers');
        Schema::dropIfExists('wallets');
    }
};
