<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\MedicalClaim;
use App\Models\Settlement;
use App\Models\TravelRequest;
use App\Enums\RequestStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()->can('dashboard.view'), 403);
        $summary = app(ReportController::class)->dashboard($request)->getData(true)['summary'];
        $pendingApproval = $request->user()->can('approval.inbox.view')
            ? ApprovalRequest::query()
                ->currentChain()
                ->pending()
                ->actionable()
                ->where('approver_user_id', $request->user()->getKey())
                ->count()
            : null;
        $pendingActions = [];
        if ($pendingApproval !== null && $pendingApproval > 0) {
            $pendingActions[] = ['key' => 'approval', 'label' => 'Approval', 'description' => 'Pengajuan menunggu tindakan approval Anda.', 'count' => $pendingApproval, 'href' => '/approvals', 'tone' => 'rose'];
        }

        $employeeIds = Employee::query()->where('user_id', $request->user()->getKey())->pluck('id');
        if ($request->user()->can('settlement.create.own')) {
            $eligibleStatuses = config('eform.settlement.allowed_source_statuses', ['advance_paid', 'settlement_required']);
            $sourceFilter = fn ($query) => $query->whereIn('status', $eligibleStatuses)->where('total_advance', '>', 0)->whereDoesntHave('settlements', fn ($settlement) => $settlement->whereNotNull('source_key'))->where(fn ($owner) => $owner->where('created_by', $request->user()->getKey())->orWhereIn('employee_id', $employeeIds));
            $settlementSources = LeaveRequest::query()->tap($sourceFilter)->count() + TravelRequest::query()->tap($sourceFilter)->count();
            if ($settlementSources > 0) {
                $pendingActions[] = ['key' => 'settlement', 'label' => 'Perlu Settlement', 'description' => 'Advance yang dapat dibuatkan settlement.', 'count' => $settlementSources, 'href' => '/settlements/create', 'tone' => 'amber'];
            }
        }
        $advanceStatuses = [RequestStatus::Approved, RequestStatus::Processing];
        $pendingLeaveAdvance = $request->user()->can('advance.process.leave')
            ? LeaveRequest::query()->whereIn('status', $advanceStatuses)->count()
            : 0;
        $pendingTravelAdvance = $request->user()->can('advance.process.travel')
            ? TravelRequest::query()->whereIn('status', $advanceStatuses)->count()
            : 0;
        if ($pendingLeaveAdvance > 0) {
            $pendingActions[] = ['key' => 'leave-advance', 'label' => 'Proses Advance Cuti', 'description' => 'Cuti yang menunggu pemrosesan advance oleh Anda.', 'count' => $pendingLeaveAdvance, 'href' => '/leaves', 'tone' => 'blue'];
        }
        if ($pendingTravelAdvance > 0) {
            $pendingActions[] = ['key' => 'travel-advance', 'label' => 'Proses Advance Dinas', 'description' => 'Perjalanan dinas yang menunggu pemrosesan advance oleh Anda.', 'count' => $pendingTravelAdvance, 'href' => '/travels', 'tone' => 'blue'];
        }
        if ($request->user()->can('settlement.complete') && $request->user()->can('settlement.review.finance')) {
            $paymentCount = Settlement::query()->where('status', RequestStatus::PaymentProcessing)->count();
            if ($paymentCount > 0) {
                $pendingActions[] = ['key' => 'payment', 'label' => 'Selesaikan Pembayaran', 'description' => 'Settlement menunggu penyelesaian pembayaran.', 'count' => $paymentCount, 'href' => '/settlements?status=payment_processing', 'tone' => 'emerald'];
            }
        }
        if ($request->user()->can('medical.payment.process')) {
            $medicalPaymentCount = MedicalClaim::query()
                ->where('status', RequestStatus::Approved)
                ->whereDoesntHave('approvalRequests', fn ($approval) => $approval->where('status', 'pending'))
                ->whereHas('approvalRequests', fn ($approval) => $approval->where('status', 'approved'))
                ->count();
            if ($medicalPaymentCount > 0) {
                $pendingActions[] = ['key' => 'medical-payment', 'label' => 'Proses Pembayaran Medical Claim', 'description' => 'Medical Claim yang menunggu diproses oleh Finance.', 'count' => $medicalPaymentCount, 'href' => '/medical-claims', 'tone' => 'emerald'];
            }
        }
        if ($request->user()->can('medical.payment.complete')) {
            $medicalCompletionCount = MedicalClaim::query()
                ->where('status', RequestStatus::PaymentProcessing)
                ->where(fn ($claim) => $claim->whereNull('payment_processed_by')->orWhere('payment_processed_by', '!=', $request->user()->getKey()))
                ->count();
            if ($medicalCompletionCount > 0) {
                $pendingActions[] = ['key' => 'medical-completion', 'label' => 'Selesaikan Medical Claim', 'description' => 'Medical Claim yang menunggu konfirmasi pembayaran.', 'count' => $medicalCompletionCount, 'href' => '/medical-claims', 'tone' => 'emerald'];
            }
        }

        return Inertia::render('Dashboard', [
            'summary' => $summary,
            'statusBreakdown' => $summary,
            'pendingApproval' => $pendingApproval,
            'pendingActions' => $pendingActions,
        ]);
    }
}
