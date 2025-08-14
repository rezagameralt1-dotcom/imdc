<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('feature_flags')) {
            Schema::create('feature_flags', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();   // e.g. EXCHANGE, DAO, VR_TOUR
                $table->boolean('enabled')->default(false);
                $table->json('meta')->nullable();   // optional context: who enabled, notes, rollout %, etc.
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // non-destructive by default
        // Schema::dropIfExists('feature_flags');
    }
};
