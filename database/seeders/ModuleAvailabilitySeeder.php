<?php

namespace Database\Seeders;

use App\Models\ModuleAvailability;
use Illuminate\Database\Seeder;

class ModuleAvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        // firstOrCreate agar konfigurasi admin tidak pernah di-reset saat re-seed.
        foreach (array_keys(ModuleAvailability::MODULES) as $module) {
            ModuleAvailability::firstOrCreate(
                ['module' => $module],
                ['is_enabled' => true, 'maintenance_message' => null],
            );
        }
    }
}
