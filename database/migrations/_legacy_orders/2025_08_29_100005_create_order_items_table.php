<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::connection("orders")->create("order_items", function (Blueprint \$table) {
            \$table->id();
            \$table->foreignId("order_id")->constrained("orders")->cascadeOnDelete();
            \$table->unsignedBigInteger("product_id"); // No FK - products table is in products DB
            \$table->integer("qty")->default(1);
            \$table->decimal("unit_price", 12, 2);
            \$table->decimal("total", 12, 2);
            \$table->json("meta")->nullable();
            \$table->timestamps();
        });
        Schema::connection("orders")->table("order_items", function (Blueprint \$table) {
            \$table->index(["order_id", "product_id"]);
        });
    }
    public function down(): void {
        Schema::connection("orders")->dropIfExists("order_items");
    }
};
