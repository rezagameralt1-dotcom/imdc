<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Guard: only create if table doesn't exist
        if (Schema::hasTable('role_user')) {
            return;
        }
        
        $usersTableExists = Schema::hasTable('users');
        
        Schema::create('role_user', function (Blueprint $table) use ($usersTableExists) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            
            // Guard: only add FK to users if users table exists
            if ($usersTableExists) {
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            } else {
                $table->unsignedBigInteger('user_id')->nullable();
            }
            
            $table->timestamps();
            $table->unique(['role_id','user_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('role_user');
    }
};
