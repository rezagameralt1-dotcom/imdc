<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin user + demo content
        if (class_exists(\Database\Seeders\AdminUserSeeder::class)) {
            $this->call(\Database\Seeders\AdminUserSeeder::class);
        }

        $this->call(\Database\Seeders\ContentSeeder::class);
    }
}

