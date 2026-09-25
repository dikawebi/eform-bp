<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Http\Requests\StoreSettlementRequest;
use App\Http\Requests\UpdateSettlementRequest;
use App\Http\Requests\UploadSettlementAttachmentRequest;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Settlement;
use App\Models\TravelRequest;
use App\Services\EmployeeVisibility;
use App\Services\Settlement\CompleteSettlement;
use App\Services\Settlement\CreateSettlement;
use App\Services\Settlement\ResolveSettlementSource;
use App\Services\Settlement\SubmitSettlement;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class SettlementController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Settlement::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:'.implode(',', RequestStatus::values())],
            'source_type' => ['nullable', 'string', 'in:leave_request,travel_request,new_join,other'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $user = $request->user();
        $query = Settlement::query()->with('employee:id,employee_number,name')->latest();
        if (! app(EmployeeVisibility::class)->hasBroadAccess($user)) {
            app(EmployeeVisibility::class)->scope($user, $query);
        }
        $query->when($search !== '', function ($builder) use ($search): void {
            $like = '%'.$search.'%';
            $builder->where(function ($nested) use ($like): void {
                $nested->where('settlement_number', 'like', $like)
                    ->orWhere('source_reference', 'like', $like)
                    ->orWhereHas('employee', fn ($employee) => $employee->where('name', 'like', $like)->orWhere('employee_number', 'like', $like));
            });
        })->when(! empty($filters['status']), fn ($builder) => $builder->where('status', $filters['status']))
            ->when(! empty($filters['source_type']), fn ($builder) => $builder->where('source_type', $filters['source_type']));

        $page = $query->paginate(15)->withQueryString();
        $page->setCollection($page->getCollection()->map(fn (Settlement $item) => [
            'id' => $item->id,
            'settlement_number' => $item->settlement_number,
            'employee' => $item->employee ? ['id' => $item->employee->id, 'name' => $item->employee->name] : null,
            'status' => $item->status->value,
            'source_type' => $item->source_type,
            'source_reference' => $item->source_reference,
            'advance_amount' => $item->advance_amount,
            'actual_amount' => $item->actual_amount,
            'difference_type' => $item->difference_type?->value,
            'difference_amount' => $item->difference_amount,
        ]));

        return Inertia::render('Settlements/Index', ['settlements' => $page, 'statuses' => RequestStatus::values(), 'filters' => ['search' => $search, 'status' => $filters['status'] ?? '', 'source_type' => $filters['source_type'] ?? '']]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Settlement::class);
        $employeeIds = Employee::where('user_id', $request->user()->id)->pluck('id');
        $canCreateOnBehalf = $request->user()->can('settlement.create.onbehalf');
        $sources = collect([
            LeaveRequest::query()->with('employee:id,employee_number,name,department,level,job_title,roster,poh_status,poh_city')->whereIn('status', config('eform.settlement.allowed_source_statuses'))->where('total_advance', '>', 0)->whereDoesntHave('settlements', fn ($q) => $q->whereNotNull('source_key'))->where(function ($q) use ($request, $employeeIds) {
                $q->where('created_by', $request->user()->id)->orWhereIn('employee_id', $employeeIds);
            })->get()->map(fn ($s) => ['source_type' => 'leave_request', 'source_id' => $s->id, 'number' => $s->request_number, 'advance' => $s->total_advance, 'employee' => $s->employee?->only(['employee_number', 'name', 'department', 'level', 'job_title', 'roster', 'poh_status', 'poh_city'])]),
            TravelRequest::query()->with('employee:id,employee_number,name,department,level,job_title,roster,poh_status,poh_city')->whereIn('status', config('eform.settlement.allowed_source_statuses'))->where('total_advance', '>', 0)->whereDoesntHave('settlements', fn ($q) => $q->whereNotNull('source_key'))->where(function ($q) use ($request, $employeeIds) {
                $q->where('created_by', $request->user()->id)->orWhereIn('employee_id', $employeeIds);
            })->get()->map(fn ($s) => ['source_type' => 'travel_request', 'source_id' => $s->id, 'number' => $s->request_number, 'advance' => $s->total_advance, 'employee' => $s->employee?->only(['employee_number', 'name', 'department', 'level', 'job_title', 'roster', 'poh_status', 'poh_city'])]),
            Employee::query()->select(['id', 'employee_number', 'name', 'department', 'level', 'job_title', 'roster', 'poh_status', 'poh_city'])
                ->where('active', true)
                ->when(! $canCreateOnBehalf, fn ($query) => $query->whereIn('id', $employeeIds))
                ->orderBy('name')->get()->flatMap(fn (Employee $employee) => collect(['new_join', 'other'])->map(fn (string $type) => [
                    'source_type' => $type,
                    'source_id' => $employee->id,
                    'number' => $employee->employee_number.' — '.$employee->name,
                    'employee' => $employee->only(['employee_number', 'name', 'department', 'level', 'job_title', 'roster', 'poh_status', 'poh_city']),
                    'advance' => '0.00',
                    'source_reference_required' => true,
                ])),
        ])->flatten(1)->values();

        return Inertia::render('Settlements/Create', ['sources' => $sources, 'categories' => ['transport', 'hotel', 'meal', 'other']]);
    }

    public function store(StoreSettlementRequest $request): RedirectResponse
    {
        $this->authorize('create', Settlement::class);
        $data = $request->validated();
        $settlement = CreateSettlement::run($data['source_type'], (int) $data['source_id'], $request->user(), $data['items'], $data['source_reference'] ?? null);

        return redirect()->route('settlements.show', $settlement)->with('success', 'Settlement draft berhasil dibuat.');
    }

    public function show(Request $request, Settlement $settlement): Response
    {
        $this->authorize('view', $settlement);
        $settlement->load(['items', 'employee', 'leaveRequest', 'travelRequest', 'attachments', 'approvalRequests.approver:id,name', 'approvalRequests.actions.actor:id,name']);
        $source = $settlement->source();
        $manualSource = ResolveSettlementSource::isManual($settlement->source_type);
        $sourceEmployee = $source instanceof Employee ? $source : $source?->employee;
        $activities = Activity::query()->with('causer:id,name')->where('subject_type', $settlement->getMorphClass())->where('subject_id', $settlement->id)->latest()->limit(50)->get();
        $snapshot = $this->employeeDisplay($settlement->employee_snapshot_json ?: ($settlement->employee?->toArray() ?? []));

        return Inertia::render('Settlements/Show', [
            'settlement' => [
                'id' => $settlement->id, 'settlement_number' => $settlement->settlement_number,
                'source_type' => $settlement->source_type, 'source_id' => $settlement->source_id,
                'source_reference' => $settlement->source_reference,
                'status' => $settlement->status->value, 'employee' => $snapshot,
                'advance_amount' => $settlement->advance_amount, 'actual_amount' => $settlement->actual_amount,
                'difference_amount' => $settlement->difference_amount, 'difference_type' => $settlement->difference_type?->value,
                'submitted_at' => $settlement->submitted_at?->toISOString(), 'completed_at' => $settlement->completed_at?->toISOString(),
                'items' => $settlement->items->map(fn ($item) => ['id' => $item->id, 'transaction_date' => $item->transaction_date, 'description' => $item->description, 'category' => $item->category, 'amount' => $item->amount, 'receipt_no' => $item->receipt_no])->values(),
            ],
            'source' => $source ? ['id' => $source->id, 'request_number' => $manualSource ? $settlement->source_reference : $source->request_number, 'source_reference' => $settlement->source_reference, 'status' => $manualSource ? null : $source->status->value, 'purpose' => $source->purpose ?? null, 'reason' => $source->reason ?? null, 'start_date' => $source->start_date?->toDateString(), 'end_date' => $source->end_date?->toDateString(), 'employee' => $sourceEmployee?->only(['employee_number', 'name', 'department'])] : null,
            'difference' => ['advance' => $settlement->advance_amount, 'actual' => $settlement->actual_amount, 'difference' => $settlement->difference_amount, 'type' => $settlement->difference_type?->value],
            'availableActions' => ['can_edit' => $request->user()->can('update', $settlement), 'can_submit' => $request->user()->can('submit', $settlement), 'can_cancel' => $request->user()->can('cancel', $settlement), 'can_complete' => $request->user()->can('complete', $settlement), 'can_upload' => $request->user()->can('upload', $settlement)],
            'timeline' => $activities->map(fn (Activity $activity) => ['actor' => $activity->causer ? ['id' => $activity->causer->id, 'name' => $activity->causer->name] : null, 'at' => $activity->created_at?->toISOString(), 'action' => $activity->description, 'comments' => data_get($activity->properties, 'comments')])->values(),
            'approval_timeline' => $settlement->approvalRequests->sortBy('step_order')->map(fn ($approval) => ['id' => $approval->id, 'step_order' => $approval->step_order, 'step_code' => $approval->step_code, 'status' => $approval->status->value, 'approver' => $approval->approver ? ['id' => $approval->approver->id, 'name' => $approval->approver->name] : null, 'acted_by' => $approval->actions->sortByDesc('id')->first()?->actor?->name, 'comments' => $approval->comments])->values(),
            'attachments' => $settlement->attachments->map(fn (Attachment $attachment) => ['id' => $attachment->id, 'document_type' => $attachment->document_type, 'original_name' => $attachment->original_name, 'mime_type' => $attachment->mime_type, 'file_size' => $attachment->file_size, 'download_url' => route('attachments.download', $attachment)])->values(),
        ]);
    }

    public function edit(Request $request, Settlement $settlement): Response
    {
        $this->authorize('update', $settlement);

        $settlement->load('items');
        $source = $settlement->source();
        $manualSource = ResolveSettlementSource::isManual($settlement->source_type);
        $sourceNumber = $manualSource
            ? $settlement->source_reference
            : $source?->request_number;

        return Inertia::render('Settlements/Edit', ['settlement' => [
            'id' => $settlement->id, 'settlement_number' => $settlement->settlement_number,
            'source_type' => $settlement->source_type, 'source_id' => $settlement->source_id,
            'source_reference' => $settlement->source_reference,
            'advance_amount' => $settlement->advance_amount,
            'source_number' => $sourceNumber,
            'employee' => $this->employeeDisplay($settlement->employee_snapshot_json ?: ($settlement->employee?->toArray() ?? [])),
            'items' => $settlement->items->map(fn ($item) => ['id' => $item->id, 'transaction_date' => $item->transaction_date, 'description' => $item->description, 'category' => $item->category, 'amount' => $item->amount, 'receipt_no' => $item->receipt_no])->values(),
        ], 'categories' => ['transport', 'hotel', 'meal', 'other']]);
    }

    private function employeeDisplay(array $employee): ?array
    {
        $display = array_intersect_key($employee, array_flip(['employee_number', 'name', 'department', 'level', 'job_title', 'roster', 'poh_status', 'poh_city', 'poh_province']));

        return $display === [] ? null : $display;
    }

    public function update(UpdateSettlementRequest $request, Settlement $settlement): RedirectResponse
    {
        $this->authorize('update', $settlement);
        DB::transaction(function () use ($request, $settlement) {
            $locked = Settlement::whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            $this->authorize('update', $locked);
            CreateSettlement::syncItems($locked, $request->validated()['items']);
            $locked->updated_by = $request->user()->id;
            $locked->save();
            activity()->performedOn($locked)->causedBy($request->user())->log('settlement.updated');
        });

        return redirect()->route('settlements.show', $settlement)->with('success', 'Settlement berhasil diperbarui.');
    }

    public function submit(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->authorize('submit', $settlement);
        SubmitSettlement::run($settlement, $request->user());

        return redirect()->route('settlements.show', $settlement)->with('success', 'Settlement berhasil disubmit.');
    }

    public function cancel(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->authorize('cancel', $settlement);
        DB::transaction(function () use ($request, $settlement) {
            $locked = Settlement::whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [RequestStatus::Draft, RequestStatus::Submitted, RequestStatus::Returned], true)) {
                throw ValidationException::withMessages(['status' => 'Settlement tidak dapat dibatalkan pada status ini.']);
            }
            $from = $locked->status;
            $locked->forceFill(['status' => RequestStatus::Cancelled, 'source_key' => null, 'updated_by' => $request->user()->id])->save();
            activity()->performedOn($locked)->causedBy($request->user())->withProperties(['from' => $from->value, 'to' => 'cancelled'])->log('settlement.cancelled');
        });

        return redirect()->route('settlements.show', $settlement);
    }

    public function complete(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->authorize('complete', $settlement);
        CompleteSettlement::run($settlement, $request->user());

        return back()->with('success', 'Settlement selesai diproses.');
    }

    public function uploadAttachment(UploadSettlementAttachmentRequest $request, Settlement $settlement): RedirectResponse
    {
        $this->authorize('upload', $settlement);
        $file = $request->file('file');
        $path = $file->store('settlement/'.$settlement->id, 'eform-private');
        $hash = hash_file('sha256', $file->getRealPath());
        try {
            DB::transaction(function () use ($request, $settlement, $file, $path, $hash): void {
                $locked = Settlement::query()->whereKey($settlement->id)->with('employee')->lockForUpdate()->firstOrFail();
                $this->authorize('upload', $locked);
                $attachment = $locked->attachments()->create(['document_type' => $request->document_type, 'original_name' => $file->getClientOriginalName(), 'stored_path' => $path, 'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'sha256_hash' => $hash, 'uploaded_by' => $request->user()->id]);
                activity()->performedOn($locked)->causedBy($request->user())->withProperties(['actor_id' => $request->user()->id, 'attachment_id' => $attachment->id, 'type' => $attachment->document_type, 'size' => $attachment->file_size, 'hash' => $hash])->log('settlement.attachment_uploaded');
            });
        } catch (\Throwable $exception) {
            Storage::disk('eform-private')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Bukti berhasil diunggah.');
    }

    public function downloadAttachment(Request $request, Attachment $attachment)
    {
        $this->authorize('download', $attachment);
        abort_unless(Storage::disk('eform-private')->exists($attachment->stored_path), 404);

        return Storage::disk('eform-private')->download($attachment->stored_path, $attachment->original_name, ['Content-Type' => $attachment->mime_type]);
    }
}
