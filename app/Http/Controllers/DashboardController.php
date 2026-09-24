<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()->can('dashboard.view'), 403);
        $summary = app(ReportController::class)->dashboard($request)->getData(true)['summary'];

        return Inertia::render('Dashboard', ['summary' => $summary, 'statusBreakdown' => $summary]);
    }
}
