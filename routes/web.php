<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ApprovalWorkflowController;
use App\Http\Controllers\AuditTimelineController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\MedicalClaimController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\TravelRequestController;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Employee;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/api/dashboard/summary', [ReportController::class, 'dashboard'])->name('reports.dashboard');
    Route::get('/reports', [ReportController::class, 'index'])->can('report.view')->name('reports.index');
    Route::get('/audit-timeline/{module}/{id}', AuditTimelineController::class)->whereNumber('id')->name('audit.timeline');

    // Phase 1 — Master Karyawan (PRD §4, §6, §7).
    Route::prefix('master')->name('master.')->group(function () {
        // Import didefinisikan sebelum route {employee} agar tidak tertangkap.
        Route::post('/employees/import', [EmployeeController::class, 'import'])
            ->can('create', Employee::class)
            ->name('employees.import');

        Route::get('/employees', [EmployeeController::class, 'index'])
            ->can('viewAny', Employee::class)
            ->name('employees.index');

        Route::get('/employees/create', [EmployeeController::class, 'create'])
            ->can('create', Employee::class)
            ->name('employees.create');

        Route::post('/employees', [EmployeeController::class, 'store'])
            ->can('create', Employee::class)
            ->name('employees.store');

        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])
            ->can('view', 'employee')
            ->name('employees.show');

        Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])
            ->can('update', 'employee')
            ->name('employees.edit');

        Route::match(['put', 'patch'], '/employees/{employee}', [EmployeeController::class, 'update'])
            ->can('update', 'employee')
            ->name('employees.update');

        // Tanpa hapus fisik: destroy menonaktifkan via active=false.
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])
            ->can('update', 'employee')
            ->name('employees.destroy');
    });

    // Phase 2 — Cuti/Izin (PRD §2 modul A, §5.1, §6). Auth diperiksa di
    // controller via LeaveRequestPolicy; tanpa route hapus fisik.
    Route::prefix('leaves')->name('leaves.')->group(function () {
        Route::get('/', [LeaveRequestController::class, 'index'])->name('index');
        Route::get('/create', [LeaveRequestController::class, 'create'])->name('create');
        Route::post('/', [LeaveRequestController::class, 'store'])->name('store');
        Route::get('/{leave}', [LeaveRequestController::class, 'show'])->name('show');
        Route::get('/{leave}/edit', [LeaveRequestController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/{leave}', [LeaveRequestController::class, 'update'])->name('update');
        Route::post('/{leave}/submit', [LeaveRequestController::class, 'submit'])->name('submit');
        Route::post('/{leave}/cancel', [LeaveRequestController::class, 'cancel'])->name('cancel');
        Route::post('/{leave}/advance', [LeaveRequestController::class, 'processAdvance'])->name('advance');
        Route::post('/{leave}/attachments', [LeaveRequestController::class, 'uploadAttachment'])->name('attachments.store');
    });

    // Phase 3 — Perjalanan Dinas (PRD §2 modul B, §5.2, §6). Auth diperiksa di
    // controller via TravelRequestPolicy; tanpa route hapus fisik.
    Route::prefix('travels')->name('travels.')->group(function () {
        Route::get('/', [TravelRequestController::class, 'index'])->name('index');
        Route::get('/create', [TravelRequestController::class, 'create'])->name('create');
        Route::post('/', [TravelRequestController::class, 'store'])->name('store');
        Route::get('/{travel}', [TravelRequestController::class, 'show'])->name('show');
        Route::get('/{travel}/edit', [TravelRequestController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/{travel}', [TravelRequestController::class, 'update'])->name('update');
        Route::post('/{travel}/submit', [TravelRequestController::class, 'submit'])->name('submit');
        Route::post('/{travel}/cancel', [TravelRequestController::class, 'cancel'])->name('cancel');
        Route::post('/{travel}/advance', [TravelRequestController::class, 'processAdvance'])->name('advance');
        Route::post('/{travel}/attachments', [TravelRequestController::class, 'uploadAttachment'])->name('attachments.store');
    });
    Route::get('/attachments/{attachment}/download', [TravelRequestController::class, 'downloadAttachment'])->name('attachments.download');

    Route::prefix('medical-claims')->name('medical-claims.')->group(function () {
        Route::get('/', [MedicalClaimController::class, 'index'])->name('index');
        Route::get('/create', [MedicalClaimController::class, 'create'])->name('create');
        Route::post('/', [MedicalClaimController::class, 'store'])->name('store');
        Route::get('/{medical_claim}', [MedicalClaimController::class, 'show'])->name('show');
        Route::get('/{medical_claim}/edit', [MedicalClaimController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/{medical_claim}', [MedicalClaimController::class, 'update'])->name('update');
        Route::post('/{medical_claim}/submit', [MedicalClaimController::class, 'submit'])->name('submit');
        Route::post('/{medical_claim}/cancel', [MedicalClaimController::class, 'cancel'])->name('cancel');
        Route::post('/{medical_claim}/complete', [MedicalClaimController::class, 'complete'])->name('complete');
        Route::post('/{medical_claim}/payment', [MedicalClaimController::class, 'payment'])->name('payment');
        Route::post('/{medical_claim}/attachments', [MedicalClaimController::class, 'uploadAttachment'])->name('attachments.store');
    });

    Route::prefix('settlements')->name('settlements.')->group(function () {
        Route::get('/', [SettlementController::class, 'index'])->name('index');
        Route::get('/create', [SettlementController::class, 'create'])->name('create');
        Route::post('/', [SettlementController::class, 'store'])->name('store');
        Route::get('/{settlement}', [SettlementController::class, 'show'])->name('show');
        Route::get('/{settlement}/edit', [SettlementController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/{settlement}', [SettlementController::class, 'update'])->name('update');
        Route::post('/{settlement}/submit', [SettlementController::class, 'submit'])->name('submit');
        Route::post('/{settlement}/cancel', [SettlementController::class, 'cancel'])->name('cancel');
        Route::post('/{settlement}/complete', [SettlementController::class, 'complete'])->can('complete', 'settlement')->name('complete');
        Route::post('/{settlement}/attachments', [SettlementController::class, 'uploadAttachment'])->name('attachments.store');
    });

    Route::prefix('approvals')->name('approvals.')->group(function () {
        Route::get('/', [ApprovalController::class, 'index'])->can('viewAny', ApprovalRequest::class)->name('index');
        Route::get('/{approval}', [ApprovalController::class, 'show'])->can('view', 'approval')->name('show');
        Route::post('/{approval}/{action}', [ApprovalController::class, 'action'])->can('act', 'approval')->name('action');
    });

    Route::prefix('settings/workflows')->name('settings.workflows.')->group(function () {
        Route::get('/', [ApprovalWorkflowController::class, 'index'])
            ->can('viewAny', ApprovalWorkflow::class)->name('index');
        Route::get('/{scope}', [ApprovalWorkflowController::class, 'index'])
            ->whereIn('scope', ['leave', 'travel', 'settlement', 'medical-claim'])
            ->can('viewAny', ApprovalWorkflow::class)->name('scope');
        Route::put('/{workflow}', [ApprovalWorkflowController::class, 'update'])
            ->can('update', 'workflow')->name('update');
    });

    Route::prefix('settings/roles')->name('settings.roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])
            ->can('viewAny', Role::class)->name('index');
        Route::post('/', [RoleController::class, 'store'])
            ->can('create', Role::class)->name('store');
        Route::put('/{role}', [RoleController::class, 'update'])
            ->can('update', 'role')->name('update');
    });
});

require __DIR__.'/auth.php';
