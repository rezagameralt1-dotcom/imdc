<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('products')->create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('draft');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('products')->dropIfExists('products');
    }
};

