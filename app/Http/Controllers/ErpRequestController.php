<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Http\Requests\StoreErpRequestRequest;
use App\Http\Requests\UpdateErpRequestRequest;
use App\Http\Requests\UploadErpAttachmentRequest;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\ErpRequest;
use App\Services\Approval\ApprovalActionAvailability;
use App\Services\EmployeeVisibility;
use App\Services\It\ErpRequestNumber;
use App\Services\It\SubmitErpRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ErpRequestController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ErpRequest::class);
        $user = $request->user();
        $query = ErpRequest::with('employee:id,employee_number,name,department')->latest();
        if (! app(EmployeeVisibility::class)->hasBroadAccess($user)) {
            app(EmployeeVisibility::class)->scope($user, $query);
        }
        $page = $query->paginate(15)->withQueryString();
        $page->setCollection($page->getCollection()->map(fn (ErpRequest $erp) => [
            'id' => $erp->id,
            'request_number' => $erp->request_number,
            'status' => $erp->status->value,
            'action_type' => $erp->action_type,
            'recipient' => $erp->employee?->only(['id', 'employee_number', 'name', 'department']) ?? ['name' => $erp->recipient_name],
        ]));

        return Inertia::render('ErpRequests/Index', ['requests' => $page, 'statuses' => RequestStatus::values(), 'canCreate' => $user->can('create', ErpRequest::class)]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', ErpRequest::class);

        $employee = Employee::query()->where('user_id', $request->user()->id)->where('active', true)->first();
        if ($employee === null) {
            return Inertia::render('ErpRequests/Unlinked', [
                'canManageEmployees' => $request->user()->can('employee.manage'),
            ]);
        }

        return Inertia::render('ErpRequests/Create', ['meta' => $this->formMeta($request)]);
    }

    public function store(StoreErpRequestRequest $request): RedirectResponse
    {
        $this->authorize('create', ErpRequest::class);
        $user = $request->user();
        $this->guardRecipient($request);

        $erp = DB::transaction(function () use ($request, $user) {
            $data = $request->validated();
            $erp = new ErpRequest();
            $erp->forceFill([
                'request_number' => ErpRequestNumber::generate(),
                'employee_id' => $data['employee_id'] ?? null,
                'recipient_name' => $data['recipient_name'] ?? null,
                'recipient_site' => $data['recipient_site'] ?? null,
                'recipient_cost_code' => $data['recipient_cost_code'] ?? null,
                'recipient_position' => $data['recipient_position'] ?? null,
                'action_type' => $data['action_type'],
                'existing_erp_username' => $data['existing_erp_username'] ?? null,
                'business_purpose' => $data['business_purpose'],
                'modules_json' => $data['modules'] ?? [],
                'status' => RequestStatus::Draft,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ])->save();

            $erp = ErpRequest::findOrFail($erp->id);
            activity()->performedOn($erp)->causedBy($user)->log('erp.created');

            return $erp;
        });

        return redirect()->route('erp-requests.show', $erp)->with('success', 'ERP request draft berhasil dibuat.');
    }

    public function show(Request $request, ErpRequest $erp_request): Response
    {
        $this->authorize('view', $erp_request);
        $erp_request->load(['employee:id,employee_number,name,department', 'attachments', 'approvalRequests.approver:id,name', 'approvalRequests.actions.actor:id,name']);
        $user = $request->user();

        $timeline = $erp_request->approvalRequests->sortBy('step_order')->map(fn ($a) => array_filter([
            'step_code' => $a->step_code, 'status' => $a->status->value,
            'actor' => $a->approver?->name,
            'acted_by' => $a->actions->sortByDesc('id')->first()?->actor?->name,
            'at' => $a->acted_at?->toISOString(), 'comments' => $a->comments,
        ], fn ($value) => $value !== null))->values()->all();
        $activities = Activity::where('subject_type', $erp_request->getMorphClass())->where('subject_id', $erp_request->id)->latest()->limit(50)->get()->map(fn ($a) => ['id' => $a->id, 'action' => $a->description, 'actor' => $a->causer?->name, 'at' => $a->created_at?->toISOString()])->values()->all();

        return Inertia::render('ErpRequests/Show', [
            'request' => $this->dto($erp_request),
            'timeline' => $timeline,
            'activities' => $activities,
            'availableActions' => [
                'can_edit' => $user->can('update', $erp_request),
                'can_submit' => $user->can('submit', $erp_request),
                'can_cancel' => $user->can('cancel', $erp_request),
                'can_upload' => $user->can('upload', $erp_request),
            ],
            'approvalActions' => ApprovalActionAvailability::for($erp_request, $user),
        ]);
    }

    public function edit(Request $request, ErpRequest $erp_request): Response
    {
        $this->authorize('update', $erp_request);
        $erp_request->load(['employee:id,employee_number,name,department,job_title', 'attachments']);

        return Inertia::render('ErpRequests/Edit', ['request' => $this->dto($erp_request), 'meta' => $this->formMeta($request)]);
    }

    public function update(UpdateErpRequestRequest $request, ErpRequest $erp_request): RedirectResponse
    {
        $this->authorize('update', $erp_request);
        $this->guardRecipient($request);

        DB::transaction(function () use ($request, $erp_request) {
            $erp = ErpRequest::lockForUpdate()->findOrFail($erp_request->id);
            $this->authorize('update', $erp);
            $data = $request->validated();
            $erp->forceFill([
                'employee_id' => $data['employee_id'] ?? null,
                'recipient_name' => $data['recipient_name'] ?? null,
                'recipient_site' => $data['recipient_site'] ?? null,
                'recipient_cost_code' => $data['recipient_cost_code'] ?? null,
                'recipient_position' => $data['recipient_position'] ?? null,
                'action_type' => $data['action_type'],
                'existing_erp_username' => $data['existing_erp_username'] ?? null,
                'business_purpose' => $data['business_purpose'],
                'modules_json' => $data['modules'] ?? [],
                'updated_by' => $request->user()->id,
            ])->save();
            activity()->performedOn($erp)->causedBy($request->user())->log('erp.updated');
        });

        return redirect()->route('erp-requests.show', $erp_request)->with('success', 'ERP request diperbarui.');
    }

    public function submit(Request $request, ErpRequest $erp_request): RedirectResponse
    {
        $this->authorize('submit', $erp_request);
        SubmitErpRequest::run($erp_request, $request->user());

        return back()->with('success', 'ERP request berhasil diajukan.');
    }

    public function cancel(Request $request, ErpRequest $erp_request): RedirectResponse
    {
        $this->authorize('cancel', $erp_request);
        DB::transaction(function () use ($request, $erp_request) {
            $erp = ErpRequest::lockForUpdate()->findOrFail($erp_request->id);
            $this->authorize('cancel', $erp);
            $from = $erp->status;
            $erp->forceFill(['status' => RequestStatus::Cancelled, 'updated_by' => $request->user()->id])->save();
            activity()->performedOn($erp)->causedBy($request->user())->withProperties(['from' => $from->value, 'to' => 'cancelled'])->log('erp.cancelled');
        });

        return back()->with('success', 'ERP request dibatalkan.');
    }

    public function uploadAttachment(UploadErpAttachmentRequest $request, ErpRequest $erp_request): RedirectResponse
    {
        $this->authorize('upload', $erp_request);
        $file = $request->file('file');
        $path = $file->store('erp/' . $erp_request->id, 'eform-private');
        try {
            DB::transaction(function () use ($request, $erp_request, $file, $path) {
                $erp = ErpRequest::lockForUpdate()->findOrFail($erp_request->id);
                $this->authorize('upload', $erp);
                $attachment = $erp->attachments()->create([
                    'document_type' => $request->validated('document_type'),
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'sha256_hash' => hash_file('sha256', $file->getRealPath()),
                    'uploaded_by' => $request->user()->id,
                ]);
                activity()->performedOn($erp)->causedBy($request->user())->withProperties(['attachment_id' => $attachment->id, 'document_type' => $attachment->document_type])->log('erp.attachment_uploaded');
            });
        } catch (\Throwable $e) {
            Storage::disk('eform-private')->delete($path);
            throw $e;
        }

        return back()->with('success', 'Lampiran berhasil diunggah.');
    }

    public function downloadAttachment(Request $request, Attachment $attachment)
    {
        $this->authorize('download', $attachment);
        abort_unless(Storage::disk('eform-private')->exists($attachment->stored_path), 404);

        return Storage::disk('eform-private')->download($attachment->stored_path, $attachment->original_name, ['Content-Type' => $attachment->mime_type]);
    }

    /**
     * Penerima dari master karyawan lain hanya untuk pemegang create.onbehalf.
     */
    protected function guardRecipient(Request $request): void
    {
        $employeeId = (int) ($request->input('employee_id') ?? 0);
        if ($employeeId === 0) {
            return;
        }
        $ownEmployeeId = (int) (Employee::query()->where('user_id', $request->user()->id)->where('active', true)->value('id') ?? 0);
        if ($employeeId !== $ownEmployeeId && ! $request->user()->can('erp_request.create.onbehalf')) {
            throw ValidationException::withMessages(['employee_id' => 'Penerima dari master karyawan lain memerlukan hak akses khusus.']);
        }
    }

    protected function dto(ErpRequest $erp): array
    {
        return [
            'id' => $erp->id,
            'request_number' => $erp->request_number,
            'status' => $erp->status->value,
            'employee_id' => $erp->employee_id,
            'employee' => $erp->employee?->only(['id', 'employee_number', 'name', 'department', 'job_title']),
            'recipient_name' => $erp->recipient_name,
            'recipient_site' => $erp->recipient_site,
            'recipient_cost_code' => $erp->recipient_cost_code,
            'recipient_position' => $erp->recipient_position,
            'action_type' => $erp->action_type,
            'existing_erp_username' => $erp->existing_erp_username,
            'business_purpose' => $erp->business_purpose,
            'modules' => $erp->modules_json ?? [],
            'attachments' => $erp->attachments->map(fn (Attachment $a) => ['id' => $a->id, 'document_type' => $a->document_type, 'original_name' => $a->original_name, 'download_url' => route('attachments.download', $a)])->values()->all(),
        ];
    }

    protected function formMeta(Request $request): array
    {
        $employees = $request->user()?->can('erp_request.create.onbehalf')
            ? Employee::query()->select(['id', 'employee_number', 'name', 'department', 'level', 'job_title', 'roster', 'poh_status', 'poh_city', 'poh_province'])->where('active', true)->orderBy('name')->limit(500)->get()
            : Employee::query()->select(['id', 'employee_number', 'name', 'department', 'level', 'job_title', 'roster', 'poh_status', 'poh_city', 'poh_province'])->where('user_id', $request->user()?->getKey())->where('active', true)->get();

        return [
            'action_types' => config('eform.erp_request.action_types', []),
            'modules' => config('eform.erp_request.modules', []),
            'allowed_documents' => config('eform.erp_request.allowed_documents', []),
            'employees' => $employees,
        ];
    }
}
