<?php

namespace Database\Seeders;

use App\Enums\RequestStatus;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\MedicalClaim;
use App\Models\Settlement;
use App\Models\TravelRequest;
use App\Models\User;
use App\Services\AdvanceProcessing;
use App\Services\Approval\ApproveApprovalRequest;
use App\Services\Leave\CalculateLeaveAdvance;
use App\Services\Leave\CalculateLeaveDays;
use App\Services\Leave\SubmitLeaveRequest;
use App\Services\Medical\CalculateMedicalClaim;
use App\Services\Medical\SubmitMedicalClaim;
use App\Services\Settlement\CreateSettlement;
use App\Services\Settlement\SubmitSettlement;
use App\Services\Travel\CalculateTravelAdvance;
use App\Services\Travel\SubmitTravelRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Creates a small, clearly-labelled demonstration data set without changing
 * employee master attributes. It deliberately uses the production submit,
 * approval, advance, and settlement actions rather than assigning a terminal
 * status directly.
 *
 * This seeder is intentionally opt-in: it is not called by DatabaseSeeder.
 */
class DemoTransactionSeeder extends Seeder
{
    private const EMAILS = [
        'applicant' => 'demo.applicant@demo.eform-bp.invalid',
        'supervisor' => 'demo.supervisor@demo.eform-bp.invalid',
        'hod' => 'demo.hod@demo.eform-bp.invalid',
        'hrga' => 'demo.hrga@demo.eform-bp.invalid',
        'finance' => 'demo.finance@demo.eform-bp.invalid',
    ];

    /** @var array<string, User> */
    private array $users = [];

    public function run(): void
    {
        // These reference seeders are additive and do not reset configured workflows.
        $this->call([RolesPermissionsSeeder::class, ApprovalWorkflowSeeder::class]);

        DB::transaction(function (): void {
            $this->users = $this->resolveDemoUsers();
            $employee = $this->users['applicant']->employee()->firstOrFail();

            $leaveDraft = $this->leave('DEMO-CUTI-DRAFT', $employee, 'DEMO cuti draft untuk demonstrasi');
            $leaveReview = $this->leave('DEMO-CUTI-REVIEW', $employee, 'DEMO cuti dalam review untuk demonstrasi');
            $leaveEligible = $this->leave('DEMO-CUTI-SETTLEMENT', $employee, 'DEMO cuti sumber settlement');
            $travelDraft = $this->travel('DEMO-DINAS-DRAFT', $employee, 'DEMO perjalanan dinas draft');
            $travelReview = $this->travel('DEMO-DINAS-REVIEW', $employee, 'DEMO perjalanan dinas dalam review');
            $travelEligible = $this->travel('DEMO-DINAS-SETTLEMENT', $employee, 'DEMO perjalanan dinas sumber settlement');

            $this->submitIfDraft($leaveReview, SubmitLeaveRequest::class);
            $this->submitIfDraft($travelReview, SubmitTravelRequest::class);
            $this->makeEligible($leaveEligible);
            $this->makeEligible($travelEligible);

            $this->settlementDraft($travelEligible);
            $this->settlementReview($leaveEligible);

            $this->medical('DEMO-MED-DRAFT', $employee, false);
            $this->medical('DEMO-MED-REVIEW', $employee, true);

            // Retain local variables so their intentional creation is obvious to static analysis/readers.
            unset($leaveDraft, $travelDraft);
        });
    }

    /** @return array<string, User> */
    private function resolveDemoUsers(): array
    {
        $existing = User::query()->whereIn('email', self::EMAILS)->with('employee')->get()->keyBy('email');
        foreach (self::EMAILS as $key => $email) {
            if (isset($existing[$email]) && $existing[$email]->employee === null) {
                throw new RuntimeException("Akun demo {$email} sudah ada tetapi tidak terhubung ke master employee.");
            }
        }

        $employees = $existing->mapWithKeys(fn (User $user, string $email) => [array_search($email, self::EMAILS, true) => $user->employee]);
        $needed = count(self::EMAILS) - $employees->count();
        if ($needed > 0) {
            $available = Employee::query()->where('active', true)->whereNull('user_id')->orderBy('employee_number')->lockForUpdate()->get();
            if ($available->count() < $needed) {
                throw new RuntimeException('DemoTransactionSeeder memerlukan lima master employee aktif yang belum terhubung ke akun. Tidak ada perubahan demo dibuat.');
            }
            // The applicant needs a non-local identity so the leave settlement
            // example has a policy-eligible advance without changing master POH.
            if (! $employees->has('applicant')) {
                $applicant = $available->firstWhere('poh_status', 'non_local');
                if ($applicant === null) {
                    throw new RuntimeException('DemoTransactionSeeder memerlukan satu master employee aktif non-lokal untuk contoh settlement cuti. Tidak ada perubahan demo dibuat.');
                }
                $employees->put('applicant', $applicant);
                $available = $available->reject(fn (Employee $candidate) => $candidate->is($applicant))->values();
            }
            foreach (array_keys(array_diff_key(self::EMAILS, $employees->all())) as $index => $key) {
                $employees->put($key, $available[$index]);
            }
        }
        if ($employees->get('applicant')?->poh_status !== 'non_local') {
            throw new RuntimeException('Master employee untuk akun demo applicant harus berstatus non-lokal agar contoh settlement cuti tetap valid. Tidak ada perubahan demo dibuat.');
        }

        $users = [];
        foreach (self::EMAILS as $key => $email) {
            $user = User::query()->firstOrCreate(['email' => $email], [
                'name' => 'DEMO '.ucfirst($key),
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'active' => true,
            ]);
            $employee = $employees->get($key);
            if ((int) $employee->user_id !== 0 && (int) $employee->user_id !== (int) $user->id) {
                throw new RuntimeException("Master employee untuk akun demo {$email} sudah terhubung ke akun lain.");
            }
            if ((int) $employee->user_id !== (int) $user->id) {
                $employee->forceFill(['user_id' => $user->id])->save();
            }
            $users[$key] = $user->fresh(['employee']);
        }

        // Only the chosen applicant's reporting links are set; no identity/profile fields are touched.
        $applicant = $users['applicant']->employee;
        $applicant->forceFill(['supervisor_id' => $users['supervisor']->employee->id, 'hod_id' => $users['hod']->employee->id])->save();

        // Operational approvers are employees too: retain their approval role
        // and add employee permissions for own Cuti/Dinas/Settlement actions.
        foreach (['applicant' => ['employee'], 'supervisor' => ['employee', 'supervisor'], 'hod' => ['employee', 'hod'], 'hrga' => ['employee', 'hrga'], 'finance' => ['employee', 'finance', 'hrga_manager']] as $key => $roles) {
            $users[$key]->syncRoles(array_unique(array_merge($users[$key]->getRoleNames()->all(), $roles)));
        }

        return $users;
    }

    private function leave(string $number, Employee $employee, string $reason): LeaveRequest
    {
        $leave = LeaveRequest::query()->firstOrNew(['request_number' => $number]);
        if (! $leave->exists) {
            $days = CalculateLeaveDays::daysForPeriod('2026-10-05', '2026-10-07');
            $cost = CalculateLeaveAdvance::amountForItem($employee->poh_status === 'local', '1', '350000');
            $leave->forceFill(['employee_id' => $employee->id, 'leave_type' => 'annual_leave', 'reason' => $reason, 'last_working_date' => '2026-10-04', 'onsite_date' => '2026-10-08', 'total_days' => $days, 'total_advance' => $cost['amount'], 'is_local' => $employee->poh_status === 'local', 'status' => RequestStatus::Draft, 'created_by' => $this->users['applicant']->id, 'updated_by' => $this->users['applicant']->id])->save();
        }
        if (! $leave->periods()->exists()) {
            $leave->periods()->create(['category' => 'annual_leave', 'start_date' => '2026-10-05', 'end_date' => '2026-10-07', 'day_count' => CalculateLeaveDays::daysForPeriod('2026-10-05', '2026-10-07'), 'notes' => 'DEMO periode cuti']);
            $policy = CalculateLeaveAdvance::amountForItem($leave->employee->poh_status === 'local', '1', '350000');
            $leave->costItems()->create(['category' => 'land_transport', 'description' => 'DEMO transport cuti', 'quantity' => '1', 'unit_price' => '350000', 'amount' => $policy['amount'], 'eligible_by_policy' => $policy['eligible']]);
            activity()->performedOn($leave)->causedBy($this->users['applicant'])->log('demo.leave_created');
        }

        return $leave;
    }

    private function travel(string $number, Employee $employee, string $purpose): TravelRequest
    {
        $travel = TravelRequest::query()->firstOrNew(['request_number' => $number]);
        if (! $travel->exists) {
            $travel->forceFill(['employee_id' => $employee->id, 'purpose' => $purpose, 'start_date' => '2026-10-12', 'end_date' => '2026-10-14', 'origin' => 'DEMO Puruk Cahu', 'destination' => 'DEMO Banjarmasin', 'is_project_trip' => false, 'total_advance' => '0.00', 'status' => RequestStatus::Draft, 'created_by' => $this->users['applicant']->id, 'updated_by' => $this->users['applicant']->id])->save();
        }
        if (! $travel->items()->exists()) {
            $amount = CalculateTravelAdvance::amountForItem('1', '750000');
            $travel->items()->create(['category' => 'land_transport', 'transaction_date' => '2026-10-12', 'origin' => 'DEMO Puruk Cahu', 'destination' => 'DEMO Banjarmasin', 'description' => 'DEMO transport dinas', 'quantity' => '1', 'unit_price' => '750000', 'amount' => $amount]);
            $travel->forceFill(['total_advance' => CalculateTravelAdvance::totalForItems([['quantity' => '1', 'unit_price' => '750000']])])->save();
            activity()->performedOn($travel)->causedBy($this->users['applicant'])->log('demo.travel_created');
        }

        return $travel;
    }

    private function submitIfDraft(LeaveRequest|TravelRequest $request, string $action): void
    {
        if ($request->status !== RequestStatus::Draft) {
            return;
        }
        Auth::login($this->users['applicant']);
        $action::run($request, $this->users['applicant']);
    }

    private function makeEligible(LeaveRequest|TravelRequest $request): void
    {
        $this->submitIfDraft($request, $request instanceof LeaveRequest ? SubmitLeaveRequest::class : SubmitTravelRequest::class);
        while ($request->fresh()->status === RequestStatus::InReview) {
            $approval = ApprovalRequest::query()->where('approvable_type', $request->getMorphClass())->where('approvable_id', $request->id)->pending()->orderBy('step_order')->firstOrFail();
            ApproveApprovalRequest::run($approval, $approval->approver);
        }
        $fresh = $request->fresh();
        if ($fresh->status === RequestStatus::Approved) {
            AdvanceProcessing::run($fresh, $this->users['hrga']);
        }
    }

    private function settlementDraft(TravelRequest $source): void
    {
        if (! Settlement::query()->where('source_key', 'travel_request:'.$source->id)->exists()) {
            CreateSettlement::run('travel_request', $source->id, $this->users['applicant'], [['transaction_date' => '2026-10-15', 'category' => 'transport', 'description' => 'DEMO realisasi transport', 'amount' => '700000', 'receipt_no' => 'DEMO-NO-ATTACHMENT']]);
        }
    }

    private function settlementReview(LeaveRequest $source): void
    {
        $settlement = Settlement::query()->where('source_key', 'leave_request:'.$source->id)->first();
        if ($settlement === null) {
            $settlement = CreateSettlement::run('leave_request', $source->id, $this->users['applicant'], [['transaction_date' => '2026-10-08', 'category' => 'transport', 'description' => 'DEMO realisasi cuti', 'amount' => '300000', 'receipt_no' => 'DEMO-NO-ATTACHMENT']]);
        }
        if ($settlement->status === RequestStatus::Draft) {
            SubmitSettlement::run($settlement, $this->users['applicant']);
        }
    }

    private function medical(string $number, Employee $employee, bool $submit): void
    {
        $claim = MedicalClaim::query()->firstOrNew(['claim_number' => $number]);
        if (! $claim->exists) {
            $claim->forceFill(['employee_id' => $employee->id, 'benefit_type' => 'rawat_jalan', 'benefit_types' => ['rawat_jalan'], 'total_amount' => '0.00', 'status' => RequestStatus::Draft, 'employee_number' => $employee->employee_number, 'employee_name' => $employee->name, 'department' => $employee->department, 'created_by' => $this->users['applicant']->id, 'updated_by' => $this->users['applicant']->id])->save();
        }
        if (! $claim->items()->exists()) {
            $claim->items()->create(['patient_name' => $employee->name, 'relationship' => 'self', 'treatment_date' => '2026-10-01', 'facility_name' => 'DEMO klinik', 'amount' => '250000']);
            CalculateMedicalClaim::run($claim->load('items'));
            activity()->performedOn($claim)->causedBy($this->users['applicant'])->log('demo.medical_created');
        }
        if ($submit && $claim->fresh()->status === RequestStatus::Draft) {
            // No attachment is created. This process-local override is restored immediately;
            // it only permits the explicitly requested attachment-free demo workflow example.
            $required = config('eform.medical.required_documents');
            config(['eform.medical.required_documents' => []]);
            try {
                Auth::login($this->users['applicant']);
                SubmitMedicalClaim::run($claim, $this->users['applicant']);
            } finally {
                config(['eform.medical.required_documents' => $required]);
            }
        }
    }
}
