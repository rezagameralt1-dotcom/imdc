<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::connection('core')->hasTable('roles')) {
            return;
        }
        Schema::connection('core')->create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::connection('core')->dropIfExists('roles');
    }
};
