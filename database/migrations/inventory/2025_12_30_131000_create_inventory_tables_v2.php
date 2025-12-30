<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getName() !== 'inventory') {
            return;
        }

        if (! Schema::connection('inventory')->hasTable('inventory')) {
            Schema::connection('inventory')->create('inventory', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('product_id')->unique();
                $table->integer('available_quantity')->default(0);
                $table->integer('reserved_quantity')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::connection('inventory')->hasTable('inventory_movements')) {
            Schema::connection('inventory')->create('inventory_movements', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('product_id');
                $table->integer('delta_available');
                $table->integer('delta_reserved');
                $table->string('reason', 255);
                $table->string('trace_id', 36)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getName() !== 'inventory') {
            return;
        }

        Schema::connection('inventory')->dropIfExists('inventory_movements');
        Schema::connection('inventory')->dropIfExists('inventory');
    }
};


