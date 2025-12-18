<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@laravel.com',
        ]);

        // Create Nova user
        User::factory()->create([
            'name' => 'Laravel Nova',
            'email' => 'nova@laravel.com',
        ]);

        // Create additional test users
        User::factory()->count(8)->create();
    }
}
