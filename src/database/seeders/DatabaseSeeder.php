<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'username' => 'test_user',
            'email' => 'test@example.com',
        ]);

        // One account per staff role for local work (specs/04 §1), password "password". Never on a
        // shared host: a known super admin password there is a takeover.
        if (! app()->environment('local', 'testing')) {
            return;
        }

        User::factory()->moderator()->create(['username' => 'test_moderator', 'email' => 'moderator@example.com']);
        User::factory()->admin()->create(['username' => 'test_admin', 'email' => 'admin@example.com']);
        User::factory()->superAdmin()->create(['username' => 'test_super_admin', 'email' => 'superadmin@example.com']);

        $this->call(BaseFeedSeeder::class);
    }
}
