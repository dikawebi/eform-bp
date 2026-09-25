<?php

namespace Tests\Feature;

use App\Models\ApprovalWorkflow;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_foundation_without_creating_or_changing_employee_identities(): void
    {
        $supervisorUser = User::factory()->create();
        $hodUser = User::factory()->create();
        $employeeUser = User::factory()->create();
        $unlinkedUser = User::factory()->create();

        $supervisor = Employee::factory()->create(['user_id' => $supervisorUser->id]);
        $hod = Employee::factory()->create(['user_id' => $hodUser->id]);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'supervisor_id' => $supervisor->id,
            'hod_id' => $hod->id,
        ]);
        $employeeCount = Employee::count();
        $userCount = User::count();

        $this->seed(FoundationSeeder::class);
        $this->seed(FoundationSeeder::class);

        $this->assertSame($employeeCount, Employee::count());
        $this->assertSame($userCount, User::count());
        $this->assertSame($employee->employee_number, $employee->fresh()->employee_number);
        $this->assertTrue($employeeUser->fresh()->hasRole('employee'));
        $this->assertTrue($supervisorUser->fresh()->hasRole('supervisor'));
        $this->assertTrue($hodUser->fresh()->hasRole('hod'));
        $this->assertFalse($unlinkedUser->fresh()->hasAnyRole(['employee', 'supervisor', 'hod']));
        $this->assertSame(4, ApprovalWorkflow::count());
        $this->assertSame('PT Borneo Prima', config('eform.company.name'));
    }

    public function test_it_does_not_reset_an_existing_workflow_configuration(): void
    {
        $this->seed(FoundationSeeder::class);
        $workflow = ApprovalWorkflow::query()->where('code', 'travel_default')->firstOrFail();
        $workflow->update(['name' => 'Workflow Dinas Kustom', 'is_active' => false]);

        $this->seed(FoundationSeeder::class);

        $this->assertSame('Workflow Dinas Kustom', $workflow->fresh()->name);
        $this->assertFalse($workflow->fresh()->is_active);
    }
}
