<?php

namespace App\Providers;

use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\MedicalClaim;
use App\Models\Settlement;
use App\Models\TravelRequest;
use App\Models\User;
use App\Policies\ApprovalRequestPolicy;
use App\Policies\ApprovalWorkflowPolicy;
use App\Policies\AttachmentPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\MedicalClaimPolicy;
use App\Policies\RolePolicy;
use App\Policies\SettlementPolicy;
use App\Policies\TravelRequestPolicy;
use App\Support\MedicalConfigValidator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        MedicalConfigValidator::validate();
        Vite::prefetch(concurrency: 3);

        // Pastikan direktori private eForm tersedia (PRD §11: storage private MVP).
        File::ensureDirectoryExists(storage_path('app/private/eform'));

        // Policy kerangka Phase 0 (model Employee menyusul di Phase 1).
        // ::class aman meski model belum ada (tidak trigger autoload).
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(LeaveRequest::class, LeaveRequestPolicy::class);
        Gate::policy(TravelRequest::class, TravelRequestPolicy::class);
        Gate::policy(MedicalClaim::class, MedicalClaimPolicy::class);
        Gate::policy(Settlement::class, SettlementPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(ApprovalRequest::class, ApprovalRequestPolicy::class);
        Gate::policy(ApprovalWorkflow::class, ApprovalWorkflowPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Relation::morphMap([
            'leave_request' => LeaveRequest::class,
            'travel_request' => TravelRequest::class,
            'settlement' => Settlement::class,
            // Reserved for Phase 6 without resolving a model that is not implemented yet.
            'medical_claim' => 'App\\Models\\MedicalClaim',
            'approval_request' => ApprovalRequest::class,
            'attachment' => Attachment::class,
            'user' => User::class,
        ]);
    }
}
