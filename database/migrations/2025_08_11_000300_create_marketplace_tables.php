<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('shops')) {
            Schema::create('shops', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->unsignedBigInteger('owner_id');
                $t->timestamps();
                $t->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('shop_id');
                $t->string('name');
                $t->string('sku')->unique();
                $t->integer('price'); // in minor units
                $t->jsonb('meta')->nullable();
                $t->timestamps();
                $t->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            });
        }
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->string('status', 24)->default('pending');
                $t->integer('total')->default(0);
                $t->timestamps();
                $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('order_id');
                $t->unsignedBigInteger('product_id');
                $t->integer('qty')->default(1);
                $t->integer('price');
                $t->timestamps();
                $t->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
                $t->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            });
        }
        if (!Schema::hasTable('inventory')) {
            Schema::create('inventory', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('product_id');
                $t->integer('stock')->default(0);
                $t->timestamps();
                $t->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('shops');
    }
};
