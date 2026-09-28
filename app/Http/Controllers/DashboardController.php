<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
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

        return Inertia::render('Dashboard', [
            'summary' => $summary,
            'statusBreakdown' => $summary,
            'pendingApproval' => $pendingApproval,
        ]);
    }
}
