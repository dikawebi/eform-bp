<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\MedicalClaim;
use App\Models\Settlement;
use App\Models\TravelRequest;
use App\Models\User;
use Database\Seeders\DemoTransactionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoTransactionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_idempotent_and_builds_demo_workflow_examples(): void
    {
        Employee::factory()->create(['active' => true, 'user_id' => null, 'poh_status' => 'non_local']);
        Employee::factory()->count(4)->create(['active' => true, 'user_id' => null]);

        $this->seed(DemoTransactionSeeder::class);
        $firstApprovalCount = ApprovalRequest::count();
        $this->seed(DemoTransactionSeeder::class);

        $this->assertSame(5, User::query()->where('email', 'like', '%@demo.eform-bp.invalid')->count());
        $this->assertSame(3, LeaveRequest::count());
        $this->assertSame(3, TravelRequest::count());
        $this->assertSame(2, Settlement::count());
        $this->assertSame(2, MedicalClaim::count());
        $this->assertSame($firstApprovalCount, ApprovalRequest::count());
        $this->assertSame(RequestStatus::InReview, LeaveRequest::where('request_number', 'DEMO-CUTI-REVIEW')->value('status'));
        $this->assertSame(RequestStatus::InReview, TravelRequest::where('request_number', 'DEMO-DINAS-REVIEW')->value('status'));
        $this->assertSame(RequestStatus::SettlementRequired, TravelRequest::where('request_number', 'DEMO-DINAS-SETTLEMENT')->value('status'));
        $this->assertSame(RequestStatus::InReview, Settlement::where('source_type', 'leave_request')->value('status'));
        $this->assertSame(RequestStatus::InReview, MedicalClaim::where('claim_number', 'DEMO-MED-REVIEW')->value('status'));
    }
}
