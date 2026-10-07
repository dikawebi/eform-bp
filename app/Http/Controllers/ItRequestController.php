<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Http\Requests\StoreItRequestRequest;
use App\Http\Requests\UpdateItRequestRequest;
use App\Http\Requests\UploadItAttachmentRequest;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\ItRequest;
use App\Services\Approval\ApprovalActionAvailability;
use App\Services\EmployeeVisibility;
use App\Services\It\ItRequestNumber;
use App\Services\It\SubmitItRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ItRequestController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ItRequest::class);
        $user = $request->user();
        $query = ItRequest::with('employee:id,employee_number,name,department')->latest();
        if (! app(EmployeeVisibility::class)->hasBroadAccess($user)) {
            app(EmployeeVisibility::class)->scope($user, $query);
        }
        $page = $query->paginate(15)->withQueryString();
        $page->setCollection($page->getCollection()->map(fn (ItRequest $it) => [
            'id' => $it->id,
            'request_number' => $it->request_number,
            'status' => $it->status->value,
            'request_type' => $it->request_type,
            'device_type' => $it->device_type,
            'priority' => $it->priority,
            'needed_date' => $it->needed_date?->toDateString(),
            'recipient' => $it->employee?->only(['id', 'employee_number', 'name', 'department']) ?? ['name' => $it->recipient_name],
        ]));

        return Inertia::render('ITRequests/Index', ['requests' => $page, 'statuses' => RequestStatus::values(), 'canCreate' => $user->can('create', ItRequest::class)]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', ItRequest::class);

        $employee = Employee::query()->where('user_id', $request->user()->id)->where('active', true)->first();
        if ($employee === null) {
            return Inertia::render('ITRequests/Unlinked', [
                'canManageEmployees' => $request->user()->can('employee.manage'),
            ]);
        }

        return Inertia::render('ITRequests/Create', ['meta' => $this->formMeta($request)]);
    }

    public function store(StoreItRequestRequest $request): RedirectResponse
    {
        $this->authorize('create', ItRequest::class);
        $user = $request->user();
        $this->guardRecipient($request);

        $it = DB::transaction(function () use ($request, $user) {
            $data = $request->validated();
            $it = new ItRequest();
            $it->forceFill([
                'request_number' => ItRequestNumber::generate(),
                'employee_id' => $data['employee_id'] ?? null,
                'recipient_name' => $data['recipient_name'] ?? null,
                'recipient_id_number' => $data['recipient_id_number'] ?? null,
                'recipient_site' => $data['recipient_site'] ?? null,
                'recipient_cost_code' => $data['recipient_cost_code'] ?? null,
                'recipient_position' => $data['recipient_position'] ?? null,
                'recipient_effective_date' => $data['recipient_effective_date'] ?? null,
                'is_new_employee' => (bool) ($data['is_new_employee'] ?? false),
                'request_type' => $data['request_type'],
                'replacement_reason' => $data['replacement_reason'] ?? null,
                'replacement_note' => $data['replacement_note'] ?? null,
                'device_type' => $data['device_type'] ?? null,
                'special_specification' => (bool) ($data['special_specification'] ?? false),
                'description' => $data['description'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'software_standard_json' => $data['software_standard'] ?? [],
                'software_optional_json' => $data['software_optional'] ?? [],
                'accessories_json' => $data['accessories'] ?? [],
                'accessory_other_note' => $data['accessory_other_note'] ?? null,
                'needed_date' => $data['needed_date'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'status' => RequestStatus::Draft,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ])->save();

            $it = ItRequest::findOrFail($it->id);
            activity()->performedOn($it)->causedBy($user)->log('it.created');

            return $it;
        });

        return redirect()->route('it-requests.show', $it)->with('success', 'IT request draft berhasil dibuat.');
    }

    public function show(Request $request, ItRequest $it_request): Response
    {
        $this->authorize('view', $it_request);
        $it_request->load(['employee:id,employee_number,name,department', 'attachments', 'approvalRequests.approver:id,name', 'approvalRequests.actions.actor:id,name']);
        $user = $request->user();

        $dto = $this->dto($it_request);
        $timeline = $it_request->approvalRequests->sortBy('step_order')->map(fn ($a) => array_filter([
            'step_code' => $a->step_code, 'status' => $a->status->value,
            'actor' => $a->approver?->name,
            'acted_by' => $a->actions->sortByDesc('id')->first()?->actor?->name,
            'at' => $a->acted_at?->toISOString(), 'comments' => $a->comments,
        ], fn ($value) => $value !== null))->values()->all();
        $activities = Activity::where('subject_type', $it_request->getMorphClass())->where('subject_id', $it_request->id)->latest()->limit(50)->get()->map(fn ($a) => ['id' => $a->id, 'action' => $a->description, 'actor' => $a->causer?->name, 'at' => $a->created_at?->toISOString()])->values()->all();

        return Inertia::render('ITRequests/Show', [
            'request' => $dto,
            'timeline' => $timeline,
            'activities' => $activities,
            'availableActions' => [
                'can_edit' => $user->can('update', $it_request),
                'can_submit' => $user->can('submit', $it_request),
                'can_cancel' => $user->can('cancel', $it_request),
                'can_upload' => $user->can('upload', $it_request),
            ],
            'approvalActions' => ApprovalActionAvailability::for($it_request, $user),
        ]);
    }

    public function edit(Request $request, ItRequest $it_request): Response
    {
        $this->authorize('update', $it_request);
        $it_request->load(['employee:id,employee_number,name,department,job_title', 'attachments']);

        return Inertia::render('ITRequests/Edit', ['request' => $this->dto($it_request), 'meta' => $this->formMeta($request)]);
    }

    public function update(UpdateItRequestRequest $request, ItRequest $it_request): RedirectResponse
    {
        $this->authorize('update', $it_request);
        $this->guardRecipient($request);

        DB::transaction(function () use ($request, $it_request) {
            $it = ItRequest::lockForUpdate()->findOrFail($it_request->id);
            $this->authorize('update', $it);
            $data = $request->validated();
            $it->forceFill([
                'employee_id' => $data['employee_id'] ?? null,
                'recipient_name' => $data['recipient_name'] ?? null,
                'recipient_id_number' => $data['recipient_id_number'] ?? null,
                'recipient_site' => $data['recipient_site'] ?? null,
                'recipient_cost_code' => $data['recipient_cost_code'] ?? null,
                'recipient_position' => $data['recipient_position'] ?? null,
                'recipient_effective_date' => $data['recipient_effective_date'] ?? null,
                'is_new_employee' => (bool) ($data['is_new_employee'] ?? false),
                'request_type' => $data['request_type'],
                'replacement_reason' => $data['replacement_reason'] ?? null,
                'replacement_note' => $data['replacement_note'] ?? null,
                'device_type' => $data['device_type'] ?? null,
                'special_specification' => (bool) ($data['special_specification'] ?? false),
                'description' => $data['description'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'software_standard_json' => $data['software_standard'] ?? [],
                'software_optional_json' => $data['software_optional'] ?? [],
                'accessories_json' => $data['accessories'] ?? [],
                'accessory_other_note' => $data['accessory_other_note'] ?? null,
                'needed_date' => $data['needed_date'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'updated_by' => $request->user()->id,
            ])->save();
            activity()->performedOn($it)->causedBy($request->user())->log('it.updated');
        });

        return redirect()->route('it-requests.show', $it_request)->with('success', 'IT request diperbarui.');
    }

    public function submit(Request $request, ItRequest $it_request): RedirectResponse
    {
        $this->authorize('submit', $it_request);
        SubmitItRequest::run($it_request, $request->user());

        return back()->with('success', 'IT request berhasil diajukan.');
    }

    public function cancel(Request $request, ItRequest $it_request): RedirectResponse
    {
        $this->authorize('cancel', $it_request);
        DB::transaction(function () use ($request, $it_request) {
            $it = ItRequest::lockForUpdate()->findOrFail($it_request->id);
            $this->authorize('cancel', $it);
            $from = $it->status;
            $it->forceFill(['status' => RequestStatus::Cancelled, 'updated_by' => $request->user()->id])->save();
            activity()->performedOn($it)->causedBy($request->user())->withProperties(['from' => $from->value, 'to' => 'cancelled'])->log('it.cancelled');
        });

        return back()->with('success', 'IT request dibatalkan.');
    }

    public function uploadAttachment(UploadItAttachmentRequest $request, ItRequest $it_request): RedirectResponse
    {
        $this->authorize('upload', $it_request);
        $file = $request->file('file');
        $path = $file->store('it/' . $it_request->id, 'eform-private');
        try {
            DB::transaction(function () use ($request, $it_request, $file, $path) {
                $it = ItRequest::lockForUpdate()->findOrFail($it_request->id);
                $this->authorize('upload', $it);
                $attachment = $it->attachments()->create([
                    'document_type' => $request->validated('document_type'),
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'sha256_hash' => hash_file('sha256', $file->getRealPath()),
                    'uploaded_by' => $request->user()->id,
                ]);
                activity()->performedOn($it)->causedBy($request->user())->withProperties(['attachment_id' => $attachment->id, 'document_type' => $attachment->document_type])->log('it.attachment_uploaded');
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
        if ($employeeId !== $ownEmployeeId && ! $request->user()->can('it_request.create.onbehalf')) {
            throw ValidationException::withMessages(['employee_id' => 'Penerima dari master karyawan lain memerlukan hak akses khusus.']);
        }
    }

    protected function dto(ItRequest $it): array
    {
        return [
            'id' => $it->id,
            'request_number' => $it->request_number,
            'status' => $it->status->value,
            'employee_id' => $it->employee_id,
            'employee' => $it->employee?->only(['id', 'employee_number', 'name', 'department', 'job_title']),
            'is_new_employee' => (bool) $it->is_new_employee,
            'recipient_name' => $it->recipient_name,
            'recipient_id_number' => $it->recipient_id_number,
            'recipient_site' => $it->recipient_site,
            'recipient_cost_code' => $it->recipient_cost_code,
            'recipient_position' => $it->recipient_position,
            'recipient_effective_date' => $it->recipient_effective_date?->toDateString(),
            'request_type' => $it->request_type,
            'replacement_reason' => $it->replacement_reason,
            'replacement_note' => $it->replacement_note,
            'device_type' => $it->device_type,
            'special_specification' => (bool) $it->special_specification,
            'description' => $it->description,
            'purpose' => $it->purpose,
            'software_standard' => $it->software_standard_json ?? [],
            'software_optional' => $it->software_optional_json ?? [],
            'accessories' => $it->accessories_json ?? [],
            'accessory_other_note' => $it->accessory_other_note,
            'needed_date' => $it->needed_date?->toDateString(),
            'priority' => $it->priority,
            'attachments' => $it->attachments->map(fn (Attachment $a) => ['id' => $a->id, 'document_type' => $a->document_type, 'original_name' => $a->original_name, 'download_url' => route('attachments.download', $a)])->values()->all(),
        ];
    }

    protected function formMeta(Request $request): array
    {
        $employees = $request->user()?->can('it_request.create.onbehalf')
            ? Employee::query()->select(['id', 'employee_number', 'name', 'department', 'level', 'job_title', 'roster', 'poh_status', 'poh_city', 'poh_province'])->where('active', true)->orderBy('name')->limit(500)->get()
            : Employee::query()->select(['id', 'employee_number', 'name', 'department', 'level', 'job_title', 'roster', 'poh_status', 'poh_city', 'poh_province'])->where('user_id', $request->user()?->getKey())->where('active', true)->get();

        return [
            'devices' => config('eform.it_request.devices', []),
            'priorities' => config('eform.it_request.priorities', []),
            'software_standard' => config('eform.it_request.software_standard', []),
            'replacement_reasons' => config('eform.it_request.replacement_reasons', []),
            'accessories' => config('eform.it_request.accessories', []),
            'allowed_documents' => config('eform.it_request.allowed_documents', []),
            'employees' => $employees,
        ];
    }
}
