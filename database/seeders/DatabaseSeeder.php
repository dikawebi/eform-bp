<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesPermissionsSeeder::class,
            ApprovalWorkflowSeeder::class,
        ]);

        $adminEmail = (string) env('ADMIN_EMAIL', 'admin@borneoprima.co.id');

        // Jangan overwrite password admin tiap seed: hanya set saat create.
        $initialPassword = (string) (env('ADMIN_INITIAL_PASSWORD') ?? '');

        if ($initialPassword === '') {
            if (app()->isProduction()) {
                throw new \RuntimeException(
                    'ADMIN_INITIAL_PASSWORD wajib diisi untuk seed admin di production.'
                );
            }

            // Fallback non-production: random, dicatat sekali tanpa membocorkan password ke log.
            $initialPassword = Str::random(32);
            $generatedRandom = true;
        } else {
            $generatedRandom = false;
        }

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Administrator',
                'password' => $initialPassword,
                'email_verified_at' => now(),
            ]
        );

        if ($admin->wasRecentlyCreated && $generatedRandom) {
            Log::warning('Seed admin dibuat dengan password acak karena ADMIN_INITIAL_PASSWORD kosong (non-production).');
        }

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}
