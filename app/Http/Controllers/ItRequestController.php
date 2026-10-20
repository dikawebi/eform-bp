<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Http\Requests\StoreItRequestRequest;
use App\Http\Requests\UpdateItRequestRequest;
use App\Http\Requests\UploadItAttachmentRequest;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\ItItemOption;
use App\Models\ItRequest;
use App\Services\Approval\ApprovalActionAvailability;
use App\Services\Approval\WithdrawRequest;
use App\Services\EmployeeVisibility;
use App\Services\It\ItRequestNumber;
use App\Services\It\SubmitItRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
        $deviceCodes = ItItemOption::query()->where('kind', 'device')->pluck('code')->all();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(RequestStatus::values())],
            'request_type' => ['nullable', 'string', Rule::in(['new_item', 'replacement'])],
            'device_types' => ['nullable', 'array', 'max:20'],
            'device_types.*' => ['string', Rule::in($deviceCodes)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', 'string', Rule::in(['request_number', 'request_type', 'status', 'created_at'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ]);
        $user = $request->user();
        $query = ItRequest::with('employee:id,employee_number,name,department');
        if (! app(EmployeeVisibility::class)->hasBroadAccess($user)) {
            app(EmployeeVisibility::class)->scope($user, $query);
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($inner) use ($like) {
                $inner->where('request_number', 'like', $like)
                    ->orWhere('recipient_name', 'like', $like)
                    ->orWhere('pr_msd_number', 'like', $like)
                    ->orWhere('device_type', 'like', $like)
                    ->orWhereHas('employee', fn ($employee) => $employee->where('name', 'like', $like)->orWhere('employee_number', 'like', $like));
            });
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['request_type'])) {
            $query->where('request_type', $filters['request_type']);
        }
        if (! empty($filters['device_types'])) {
            $query->whereIn('device_type', $filters['device_types']);
        }
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        if ($dateFrom && $dateTo) {
            $query->whereDate('created_at', '>=', $dateFrom)->whereDate('created_at', '<=', $dateTo);
        } elseif ($dateFrom) {
            $query->whereDate('created_at', $dateFrom);
        }
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';
        $query->orderBy($sort, $direction)->orderBy('id', $direction);
        $page = $query->paginate(15)->withQueryString();
        $page->setCollection($page->getCollection()->map(fn (ItRequest $it) => [
            'id' => $it->id,
            'request_number' => $it->request_number,
            'pr_msd_number' => $it->pr_msd_number,
            'can_print' => $it->status === RequestStatus::Completed && $user->can('it_request.review'),
            'status' => $it->status->value,
            'request_type' => $it->request_type,
            'device_type' => $it->device_type,
            'priority' => $it->priority,
            'needed_date' => $it->needed_date?->toDateString(),
            'created_at' => $it->created_at?->toISOString(),
            'recipient' => $it->employee?->only(['id', 'employee_number', 'name', 'department']) ?? ['name' => $it->recipient_name],
        ]));

        return Inertia::render('ITRequests/Index', [
            'requests' => $page, 'statuses' => RequestStatus::values(), 'canCreate' => $user->can('create', ItRequest::class),
            'filters' => ['search' => $search, 'status' => $filters['status'] ?? '', 'request_type' => $filters['request_type'] ?? '', 'device_types' => $filters['device_types'] ?? [], 'date_from' => $dateFrom ?? '', 'date_to' => $dateTo ?? ''],
            'device_options' => self::itemOptions('device'),
            'sort' => $sort, 'direction' => $direction,
        ]);
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
        $timeline = $this->approvalTimeline($it_request);
        $activities = Activity::where('subject_type', $it_request->getMorphClass())->where('subject_id', $it_request->id)->latest()->limit(50)->get()->map(fn ($a) => ['id' => $a->id, 'action' => $a->description, 'actor' => $a->causer?->name, 'at' => $a->created_at?->toISOString()])->values()->all();

        return Inertia::render('ITRequests/Show', [
            'request' => $dto,
            'meta' => ['device_options' => self::itemOptions('device'), 'accessory_options' => self::itemOptions('accessory'), 'option_labels' => ItItemOption::query()->pluck('label', 'code')->all()],
            'timeline' => $timeline,
            'activities' => $activities,
            'availableActions' => [
                'can_edit' => $user->can('update', $it_request),
                'can_submit' => $user->can('submit', $it_request),
                'can_cancel' => $user->can('cancel', $it_request),
                'can_upload' => $user->can('upload', $it_request),
                'can_print' => $user->can('print', $it_request),
                'can_fill_pr' => $user->can('fillPrNumber', $it_request),
            ],
            'approvalActions' => ApprovalActionAvailability::for($it_request, $user),
        ]);
    }

    /**
     * Dokumen cetak serah terima ke Commercial sebagai dasar PR.
     * Hanya untuk request yang sudah completed.
     */
    public function print(Request $request, ItRequest $it_request): Response
    {
        $this->authorize('print', $it_request);

        $it_request->load(['employee:id,employee_number,name,department,job_title', 'approvalRequests.approver:id,name', 'approvalRequests.actions.actor:id,name']);

        return Inertia::render('ITRequests/Print', [
            'request' => $this->dto($it_request),
            'meta' => [
                'device_options' => self::itemOptions('device'),
                'accessory_options' => self::itemOptions('accessory'),
                'option_labels' => ItItemOption::query()->pluck('label', 'code')->all(),
                'software_standard' => config('eform.it_request.software_standard', []),
            ],
            'timeline' => $this->approvalTimeline($it_request),
            'company' => config('eform.company', []),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function approvalTimeline(ItRequest $it_request): array
    {
        return $it_request->approvalRequests->sortBy('step_order')->map(fn ($a) => array_filter([
            'step_code' => $a->step_code, 'status' => $a->status->value,
            'actor' => $a->approver?->name,
            'acted_by' => $a->actions->sortByDesc('id')->first()?->actor?->name,
            'at' => $a->acted_at?->toISOString(), 'comments' => $a->comments,
        ], fn ($value) => $value !== null))->values()->all();
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
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:2000']])['reason'] ?? null;
        DB::transaction(function () use ($request, $it_request, $reason) {
            $it = ItRequest::lockForUpdate()->findOrFail($it_request->id);
            $this->authorize('cancel', $it);
            if (! in_array($it->status, [RequestStatus::Draft, RequestStatus::Submitted, RequestStatus::Returned, RequestStatus::InReview], true)) {
                throw ValidationException::withMessages(['status' => 'IT request pada status ini tidak dapat dibatalkan.']);
            }
            $from = $it->status;
            $withdraw = WithdrawRequest::run($it, $request->user(), $reason);
            $it->forceFill(['status' => RequestStatus::Cancelled, 'updated_by' => $request->user()->id])->save();
            activity()->performedOn($it)->causedBy($request->user())->withProperties(array_filter(['from' => $from->value, 'to' => 'cancelled', 'withdrawn_from_review' => $from === RequestStatus::InReview, 'approved_count' => $withdraw['approved_count'], 'reason' => $reason], fn ($value) => $value !== null))->log('it.cancelled');
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

    /**
     * Nomor PR MSD diisi IT setelah request completed; perubahan masuk audit log.
     */
    public function updatePrNumber(Request $request, ItRequest $it_request): RedirectResponse
    {
        $this->authorize('fillPrNumber', $it_request);
        $data = $request->validate(['pr_msd_number' => ['nullable', 'string', 'max:100']]);
        $number = trim((string) ($data['pr_msd_number'] ?? '')) ?: null;
        $old = $it_request->pr_msd_number;

        DB::transaction(function () use ($request, $it_request, $number, $old) {
            $it = ItRequest::lockForUpdate()->findOrFail($it_request->id);
            $it->forceFill(['pr_msd_number' => $number, 'updated_by' => $request->user()->id])->save();
            activity()->performedOn($it)->causedBy($request->user())->withProperties(['old' => $old, 'new' => $number])->log('it.pr_msd_number_updated');
        });

        return back()->with('success', $number ? 'Nomor PR MSD tersimpan.' : 'Nomor PR MSD dikosongkan.');
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
            'pr_msd_number' => $it->pr_msd_number,
            'submitted_at' => $it->submitted_at?->toISOString(),
            'approved_at' => $it->approved_at?->toISOString(),
            'completed_at' => $it->completed_at?->toISOString(),
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
            'device_options' => $this->itemOptions('device'),
            'accessory_options' => $this->itemOptions('accessory'),
            'allowed_documents' => config('eform.it_request.allowed_documents', []),
            'employees' => $employees,
        ];
    }

    /**
     * Opsi master perangkat untuk form & pelabelan ulang (fallback ke kode bila nonaktif).
     *
     * @return list<array{value:string,label:string,requires_note:bool}>
     */
    public static function itemOptions(string $kind): array
    {
        return ItItemOption::query()->where('kind', $kind)->active()->ordered()->get()
            ->map(fn (ItItemOption $option) => ['value' => $option->code, 'label' => $option->label, 'requires_note' => (bool) $option->requires_note])
            ->values()->all();
    }
}
