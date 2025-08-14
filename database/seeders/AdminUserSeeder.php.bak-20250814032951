<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class AdminUserSeeder extends Seeder {
    public function run(): void {
        User::updateOrCreate(
            ["email"=>"admin@imdc.local"],
            ["name"=>"Admin","password"=>Hash::make("Admin@123456"),"email_verified_at"=>now(), "remember_token"=>Str::random(10)]
        );
    }
}