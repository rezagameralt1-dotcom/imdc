<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('legal_cases')) {
            Schema::create('legal_cases', function (Blueprint $t) {
                $t->id();
                $t->string('case_no')->unique();
                $t->unsignedBigInteger('owner_id')->nullable();
                $t->string('status', 24)->default('open');
                $t->timestamps();
                $t->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_cases');
    }
};
