<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MorphCompatibilityMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_legacy_fqcn_values_are_normalized_to_aliases(): void
    {
        DB::table('approval_requests')->insert([
            'approvable_type' => 'App\\Models\\TravelRequest', 'approvable_id' => 999,
            'step_order' => 1, 'step_code' => 'test', 'approver_role' => 'test', 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('attachments')->insert([
            'attachable_type' => 'App\\Models\\LeaveRequest', 'attachable_id' => 999,
            'original_name' => 'legacy.pdf', 'stored_path' => 'legacy/legacy.pdf', 'file_size' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('activity_log')->insert([
            'subject_type' => 'App\\Models\\Settlement', 'subject_id' => 999,
            'causer_type' => 'App\\Models\\User', 'causer_id' => 999,
            'description' => 'legacy', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_23_170000_normalize_morph_types.php');
        $migration->up();

        $this->assertDatabaseHas('approval_requests', ['approvable_type' => 'travel_request']);
        $this->assertDatabaseHas('attachments', ['attachable_type' => 'leave_request']);
        $this->assertDatabaseHas('activity_log', ['subject_type' => 'settlement', 'causer_type' => 'user']);
    }
}
