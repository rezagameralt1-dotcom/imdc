<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getName() !== 'pgsql') {
            return;
        }

        Schema::create('shop_customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('shop_customer_id', 36)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('shop_customers');
    }
};
