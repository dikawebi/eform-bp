<?php

namespace App\Http\Controllers;

use App\Enums\LeavePeriodCategory;
use App\Enums\RequestStatus;
use App\Http\Requests\ProcessAdvanceRequest;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Http\Requests\UpdateLeaveRequestRequest;
use App\Http\Requests\UploadLeaveAttachmentRequest;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\AdvanceProcessing;
use App\Services\Approval\ApprovalActionAvailability;
use App\Services\EmployeeVisibility;
use App\Services\Leave\CalculateLeaveAdvance;
use App\Services\Leave\CalculateLeaveDays;
use App\Services\Leave\LeaveRequestNumber;
use App\Services\Leave\SubmitLeaveRequest;
use App\Services\Settlement\SettlementSourceAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Cuti/Izin Phase 2 (PRD §2 modul A, §6, §7).
 *
 * - Validasi via Form Request; authorization via LeaveRequestPolicy.
 * - Total + transition server-side dalam DB transaction + activity log.
 * - Tanpa hapus fisik (tidak ada destroy).
 * - M-01: upload lampiran cuti/dinas formal (UI + validasi file + private
 *   download) SENGAJA di-defer ke Phase 5. Tabel `attachments` generik
 *   (morph) sudah disiapkan migrasi minimal agar Phase 5 tinggal pakai.
 */
class LeaveRequestController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = (string) ($validated['status'] ?? '');

        $user = $request->user();

        $leaves = LeaveRequest::query()
            ->with(['employee:id,employee_number,name,department'])
            ->when(! app(EmployeeVisibility::class)->hasBroadAccess($user), fn ($query) => app(EmployeeVisibility::class)->scope($user, $query))
            ->when($search !== '', function ($query) use ($search) {
                $like = "%{$search}%";
                $query->where(function ($inner) use ($like) {
                    $inner->where('request_number', 'like', $like)
                        ->orWhere('employee_name', 'like', $like);
                });
            })
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Leaves/Index', [
            'leaves' => $leaves,
            'filters' => ['search' => $search, 'status' => $status],
            'statuses' => RequestStatus::values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', LeaveRequest::class);

        $linked = Employee::query()->where('user_id', $request->user()->id)->where('active', true)->exists();
        if (! $linked && ! $request->user()->can('leave.create.onbehalf')) {
            return Inertia::render('Leaves/Unlinked', [
                'canManageEmployees' => $request->user()->can('employee.manage'),
            ]);
        }

        return Inertia::render('Leaves/Create', [
            'meta' => $this->formMeta($request),
        ]);
    }

    public function store(StoreLeaveRequestRequest $request): RedirectResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $validated = $request->validated();
        $user = $request->user();

        // Retry sekali untuk race nomor unik (CUTI-YYYYMM-0001 atomik).
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $leave = DB::transaction(function () use ($validated, $user) {
                    $employee = $this->resolveEmployee($user, isset($validated['employee_id']) ? (int) $validated['employee_id'] : null);
                    $isLocal = $employee->poh_status === 'local';

                    $leave = new LeaveRequest(Arr::only($validated, [
                        'leave_type', 'reason', 'last_working_date', 'onsite_date',
                    ]));
                    $leave->request_number = LeaveRequestNumber::generate();
                    $leave->employee_id = $employee->getKey();
                    $leave->is_local = $isLocal;
                    $leave->status = RequestStatus::Draft;
                    $leave->total_days = 0;
                    $leave->total_advance = 0;
                    $leave->created_by = $user->getKey();
                    $leave->updated_by = $user->getKey();
                    $leave->save();

                    $this->syncPeriods($leave, $validated['periods'] ?? []);
                    $this->syncCostItems($leave, $validated['cost_items'] ?? [], $isLocal);

                    $fresh = $leave->refresh();

                    // A5: audit di dalam transaction agar atomik dengan data.
                    activity()
                        ->performedOn($fresh)
                        ->causedBy($user)
                        ->withProperties([
                            'request_number' => $fresh->request_number,
                            'total_days' => $fresh->total_days,
                            'total_advance' => (float) $fresh->total_advance,
                        ])
                        ->log('leave.created');

                    return $fresh;
                });

                return redirect()
                    ->route('leaves.show', $leave)
                    ->with('success', 'Pengajuan cuti berhasil dibuat sebagai draft.');
            } catch (QueryException $e) {
                if ($this->isDuplicateEntry($e) && $attempt < 3) {
                    continue;
                }

                throw $e;
            }
        }

        throw ValidationException::withMessages([
            'request_number' => 'Gagal membuat nomor dokumen, silakan coba lagi.',
        ]);
    }

    public function show(Request $request, LeaveRequest $leave): Response
    {
        $this->authorize('view', $leave);

        $leave->load(['employee:id,employee_number,name,department,level,job_title,roster,poh_status,poh_city,poh_province', 'periods', 'costItems', 'attachments', 'approvalRequests.approver:id,name', 'approvalRequests.actions.actor:id,name']);

        $activities = Activity::query()
            ->where('subject_type', $leave->getMorphClass())
            ->where('subject_id', $leave->getKey())
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $attachments = $leave->attachments->map(fn (Attachment $attachment) => [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'document_type' => $attachment->document_type,
            'mime_type' => $attachment->mime_type,
            'file_size' => $attachment->file_size,
            'download_url' => route('attachments.download', $attachment),
        ])->values()->all();

        $leaveDto = [
            'id' => $leave->id,
            'request_number' => $leave->request_number,
            'employee_id' => $leave->employee_id,
            'leave_type' => $leave->leave_type,
            'reason' => $leave->reason,
            'last_working_date' => $leave->last_working_date?->toDateString(),
            'onsite_date' => $leave->onsite_date?->toDateString(),
            'is_local' => (bool) $leave->is_local,
            'total_days' => $leave->total_days,
            'total_advance' => $leave->total_advance,
            'status' => $leave->status->value,
            'submitted_at' => $leave->submitted_at?->toISOString(),
            'approved_at' => $leave->approved_at?->toISOString(),
            'completed_at' => $leave->completed_at?->toISOString(),
            'employee' => $leave->employee?->only(['id', 'employee_number', 'name', 'department', 'level', 'job_title', 'roster', 'poh_status', 'poh_city', 'poh_province']),
            'periods' => $leave->periods->map(fn ($period) => [
                'id' => $period->id, 'category' => $period->category, 'start_date' => $period->start_date?->toDateString(),
                'end_date' => $period->end_date?->toDateString(), 'day_count' => $period->day_count, 'notes' => $period->notes,
            ])->values()->all(),
            'cost_items' => $leave->costItems->map(fn ($item) => [
                'id' => $item->id, 'category' => $item->category, 'description' => $item->description,
                'quantity' => $item->quantity, 'unit_price' => $item->unit_price, 'amount' => $item->amount,
                'origin' => $item->origin, 'destination' => $item->destination, 'flight_destination' => $item->flight_destination,
                'service_date' => $item->service_date?->toDateString(), 'check_in_date' => $item->check_in_date?->toDateString(),
                'check_out_date' => $item->check_out_date?->toDateString(), 'departure_time' => $item->departure_time ? substr((string) $item->departure_time, 0, 5) : null,
                'eligible_by_policy' => (bool) $item->eligible_by_policy,
            ])->values()->all(),
            'attachments' => $attachments,
        ];

        $timeline = $leave->approvalRequests->sortBy('step_order')->map(fn ($approval) => [
            'id' => $approval->id, 'step_order' => $approval->step_order, 'step_code' => $approval->step_code,
            'approver_role' => $approval->approver_role, 'status' => $approval->status->value,
            'approver' => $approval->approver ? ['id' => $approval->approver->id, 'name' => $approval->approver->name] : null,
            'acted_by' => $approval->actions->sortByDesc('id')->first()?->actor ? ['id' => $approval->actions->sortByDesc('id')->first()->actor->id, 'name' => $approval->actions->sortByDesc('id')->first()->actor->name] : null,
            'due_at' => $approval->due_at?->toISOString(), 'acted_at' => $approval->acted_at?->toISOString(), 'comments' => $approval->comments,
        ])->values()->all();

        $allowedActivityProperties = ['from', 'to', 'comments', 'reason', 'total_days', 'total_advance', 'action', 'step_code'];
        $activityDto = $activities->map(fn (Activity $activity) => [
            'id' => $activity->id, 'description' => $activity->description, 'created_at' => $activity->created_at?->toISOString(),
            'actor' => $activity->causer ? ['id' => $activity->causer->id, 'name' => $activity->causer->name] : null,
            'properties' => Arr::only($activity->properties->toArray(), $allowedActivityProperties),
        ])->values()->all();

        return Inertia::render('Leaves/Show', [
            'leave' => $leaveDto,
            'requires_settlement' => $leave->needsSettlement(),
            'activities' => $activityDto,
            'employee_snapshot' => $leave->status !== RequestStatus::Draft ? Arr::only((array) $leave->employee_snapshot_json, ['employee_number', 'name', 'department', 'level', 'job_title', 'roster', 'employment_status', 'poh_status', 'poh_city', 'poh_province']) : null,
            'timeline' => $timeline,
            'availableActions' => [
                'can_edit' => (bool) $request->user()->can('update', $leave),
                'can_submit' => (bool) $request->user()->can('submit', $leave),
                'can_cancel' => (bool) $request->user()->can('cancel', $leave),
                'can_process_advance' => (bool) $request->user()->can('processAdvance', $leave),
                'can_upload' => (bool) $request->user()->can('upload', $leave),
            ],
            'approvalActions' => ApprovalActionAvailability::for($leave, $request->user()),
            'canCreateSettlement' => SettlementSourceAvailability::for($leave, $request->user()),
        ]);
    }

    public function edit(Request $request, LeaveRequest $leave): Response
    {
        $this->authorize('update', $leave);

        $leave->load(['employee:id,employee_number,name,department,level,job_title,roster,poh_status,poh_city,poh_province', 'periods', 'costItems']);

        return Inertia::render('Leaves/Edit', [
            'leave' => [
                'id' => $leave->id, 'employee_id' => $leave->employee_id, 'leave_type' => $leave->leave_type,
                'reason' => $leave->reason, 'last_working_date' => $leave->last_working_date?->toDateString(),
                'onsite_date' => $leave->onsite_date?->toDateString(), 'is_local' => (bool) $leave->is_local,
                'periods' => $leave->periods->map(fn ($period) => ['id' => $period->id, 'category' => $period->category, 'start_date' => $period->start_date?->toDateString(), 'end_date' => $period->end_date?->toDateString(), 'notes' => $period->notes])->values()->all(),
                'cost_items' => $leave->costItems->map(fn ($item) => ['id' => $item->id, 'category' => $item->category, 'description' => $item->description, 'quantity' => $item->quantity, 'unit_price' => $item->unit_price, 'origin' => $item->origin, 'destination' => $item->destination, 'flight_destination' => $item->flight_destination, 'service_date' => $item->service_date?->toDateString(), 'check_in_date' => $item->check_in_date?->toDateString(), 'check_out_date' => $item->check_out_date?->toDateString(), 'departure_time' => $item->departure_time ? substr((string) $item->departure_time, 0, 5) : null])->values()->all(),
            ],
            'meta' => $this->formMeta($request),
        ]);
    }

    public function update(UpdateLeaveRequestRequest $request, LeaveRequest $leave): RedirectResponse
    {
        // Policy update = owner + draft/returned (approved immutable → 403).
        $this->authorize('update', $leave);

        $validated = $request->validated();
        $user = $request->user();

        // A1: cegah TOCTOU — kunci baris + cek ulang status + authorize ulang
        // di dalam transaction sebelum syncPeriods/syncCostItems.
        DB::transaction(function () use ($validated, $user, $leave) {
            $locked = LeaveRequest::query()
                ->whereKey($leave->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, [RequestStatus::Draft, RequestStatus::Returned], true)) {
                abort(403, 'Pengajuan pada status ini tidak dapat diubah.');
            }

            $this->authorize('update', $locked);

            $employee = $locked->employee ?? Employee::query()->findOrFail($locked->employee_id);
            $isLocal = $employee->poh_status === 'local';

            $locked->fill(Arr::only($validated, [
                'leave_type', 'reason', 'last_working_date', 'onsite_date',
            ]));
            // is_local ikut master terbaru (server-side, bukan dari frontend).
            $locked->is_local = $isLocal;
            $locked->updated_by = $user->getKey();
            $locked->save();

            $this->syncPeriods($locked, $validated['periods'] ?? []);
            $this->syncCostItems($locked, $validated['cost_items'] ?? [], $isLocal);

            $locked->refresh();

            // A5: audit di dalam transaction agar atomik dengan data.
            activity()
                ->performedOn($locked)
                ->causedBy($user)
                ->withProperties([
                    'request_number' => $locked->request_number,
                    'total_days' => $locked->total_days,
                    'total_advance' => (float) $locked->total_advance,
                ])
                ->log('leave.updated');
        });

        return redirect()
            ->route('leaves.show', $leave)
            ->with('success', 'Pengajuan cuti berhasil diperbarui.');
    }

    /**
     * Submit draft/returned → submitted + snapshot (PRD §6.2-6.3).
     */
    public function submit(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorize('submit', $leave);

        try {
            SubmitLeaveRequest::run($leave, $request->user());
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()
            ->route('leaves.show', $leave)
            ->with('success', 'Pengajuan cuti berhasil disubmit.');
    }

    public function processAdvance(ProcessAdvanceRequest $request, LeaveRequest $leave): RedirectResponse
    {
        AdvanceProcessing::run($leave, $request->user());

        return back()->with('success', 'Advance cuti berhasil diproses.');
    }

    public function uploadAttachment(UploadLeaveAttachmentRequest $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorize('upload', $leave);
        $file = $request->file('file');
        $path = $file->store('leave/'.$leave->id, 'eform-private');
        try {
            DB::transaction(function () use ($request, $leave, $file, $path): void {
                $locked = LeaveRequest::query()->whereKey($leave->id)->lockForUpdate()->firstOrFail();
                $this->authorize('upload', $locked);
                $attachment = $locked->attachments()->create([
                    'document_type' => $request->validated('document_type'), 'original_name' => $file->getClientOriginalName(),
                    'stored_path' => $path, 'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(),
                    'sha256_hash' => hash_file('sha256', $file->getRealPath()), 'uploaded_by' => $request->user()->id,
                ]);
                activity()->performedOn($locked)->causedBy($request->user())->withProperties([
                    'attachment_id' => $attachment->id, 'document_type' => $attachment->document_type,
                    'hash' => $attachment->sha256_hash,
                ])->log('leave.attachment_uploaded');
            });
        } catch (\Throwable $e) {
            Storage::disk('eform-private')->delete($path);
            throw $e;
        }

        return back()->with('success', 'Lampiran berhasil diunggah.');
    }

    /**
     * Cancel draft/submitted/returned → cancelled (tanpa hapus fisik).
     */
    public function cancel(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorize('cancel', $leave);

        $user = $request->user();

        DB::transaction(function () use ($user, $leave) {
            $leave->refresh();

            if (! in_array($leave->status, [RequestStatus::Draft, RequestStatus::Submitted, RequestStatus::Returned], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Pengajuan pada status ini tidak dapat dibatalkan.',
                ]);
            }

            $from = $leave->status;

            $leave->forceFill([
                'status' => RequestStatus::Cancelled,
                'updated_by' => $user->getKey(),
            ])->save();

            activity()
                ->performedOn($leave)
                ->causedBy($user)
                ->withProperties([
                    'request_number' => $leave->request_number,
                    'from' => $from->value,
                    'to' => RequestStatus::Cancelled->value,
                ])
                ->log('leave.cancelled');
        });

        return redirect()
            ->route('leaves.show', $leave)
            ->with('success', 'Pengajuan cuti dibatalkan.');
    }

    /**
     * Resolve employee pemilik pengajuan. employee_id dari frontend tidak
     * dipercaya: untuk karyawan lain wajib leave.create.onbehalf
     * (admin + hrga_manager saja). leave.view.all TIDAK cukup (PRD §11).
     *
     * @throws ValidationException
     */
    protected function resolveEmployee(mixed $user, ?int $requestedId): Employee
    {
        if ($requestedId !== null) {
            $isOwn = Employee::query()
                ->whereKey($requestedId)
                ->where('user_id', $user->getKey())
                ->exists();

            if ($isOwn) {
                $own = Employee::query()->whereKey($requestedId)->firstOrFail();

                if (! $own->active) {
                    throw ValidationException::withMessages([
                        'employee_id' => 'Karyawan yang dipilih tidak aktif.',
                    ]);
                }

                return $own;
            }

            // Bukan milik sendiri → wajib on-behalf, tanpa kecuali.
            if (! $user->can('leave.create.onbehalf')) {
                abort(403, 'Tidak berhak membuat pengajuan untuk karyawan lain.');
            }

            /** @var Employee $employee */
            $employee = Employee::query()->findOrFail($requestedId);

            if (! $employee->active) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Karyawan yang dipilih tidak aktif.',
                ]);
            }

            return $employee;
        }

        $own = Employee::query()
            ->where('user_id', $user->getKey())
            ->where('active', true)
            ->first();

        if ($own === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'Akun Anda belum terhubung ke data karyawan aktif.',
            ]);
        }

        return $own;
    }

    /**
     * Ganti seluruh periode lalu hitung day_count inklusif + total_days server-side.
     *
     * @param  array<int, array<string, mixed>>  $periods
     */
    protected function syncPeriods(LeaveRequest $leave, array $periods): void
    {
        $leave->periods()->delete();

        $totalDays = 0;

        foreach ($periods as $row) {
            $dayCount = CalculateLeaveDays::daysForPeriod(
                (string) ($row['start_date'] ?? ''),
                (string) ($row['end_date'] ?? ''),
            );

            // day_count server-side: di luar fillable → forceFill agar tidak
            // terbuang mass-assignment guard.
            $period = $leave->periods()->create([
                'category' => $row['category'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'notes' => $row['notes'] ?? null,
            ]);
            $period->forceFill(['day_count' => $dayCount])->save();

            // Onsite sebelum cuti dicatat sebagai penanda tanggal, bukan hari
            // cuti yang dikonsumsi pada total formulir.
            if (($row['category'] ?? null) !== LeavePeriodCategory::Onsite->value) {
                $totalDays += $dayCount;
            }
        }

        $leave->forceFill(['total_days' => $totalDays])->save();
    }

    /**
     * Ganti seluruh item biaya lalu hitung amount + eligible + total server-side.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function syncCostItems(LeaveRequest $leave, array $items, bool $isLocal): void
    {
        $leave->costItems()->delete();

        // Akumulasi string (bcadd) agar presisi, bukan float mentah (A3).
        $totalStr = '0';

        foreach ($items as $row) {
            $quantity = $row['quantity'] ?? 0;
            if (($row['category'] ?? null) === 'hotel' && filled($row['check_in_date'] ?? null) && filled($row['check_out_date'] ?? null)) {
                $quantity = CarbonImmutable::parse($row['check_in_date'])->diffInDays(CarbonImmutable::parse($row['check_out_date']));
            }
            $result = CalculateLeaveAdvance::amountForItem(
                $isLocal,
                $quantity,
                $row['unit_price'] ?? 0,
            );

            // amount + eligible server-side: di luar fillable → forceFill.
            $item = $leave->costItems()->create([
                'category' => $row['category'],
                'description' => $row['description'],
                'quantity' => $quantity,
                'unit_price' => $row['unit_price'],
                'origin' => $row['origin'] ?? null,
                'destination' => $row['destination'] ?? null,
                'flight_destination' => $row['flight_destination'] ?? null,
                'service_date' => $row['service_date'] ?? null,
                'check_in_date' => $row['check_in_date'] ?? null,
                'check_out_date' => $row['check_out_date'] ?? null,
                'departure_time' => $row['departure_time'] ?? null,
            ]);
            $item->forceFill([
                'amount' => $result['amount'],
                'eligible_by_policy' => $result['eligible'],
            ])->save();

            $amountStr = number_format((float) $result['amount'], 2, '.', '');
            $totalStr = function_exists('bcadd')
                ? bcadd($totalStr, $amountStr, 2)
                : number_format(((float) $totalStr) + ((float) $amountStr), 2, '.', '');
        }

        $leave->forceFill(['total_advance' => $totalStr])->save();
    }

    /**
     * Meta opsi form dari config + enum (tidak hard-code di controller).
     *
     * @return array<string, mixed>
     */
    protected function formMeta(Request $request): array
    {
        $periodOptions = [];
        foreach (LeavePeriodCategory::cases() as $case) {
            $periodOptions[] = ['value' => $case->value, 'label' => $case->label()];
        }

        $costOptions = [];
        foreach ((array) config('eform.leave_cost_categories', []) as $value) {
            $costOptions[] = ['value' => $value, 'label' => ucwords(str_replace('_', ' ', (string) $value))];
        }

        $employees = [];
        // A2: daftar karyawan lain hanya untuk pemegang leave.create.onbehalf
        // (admin + hrga_manager). leave.view.all TIDAK cukup.
        if ($request->user()?->can('leave.create.onbehalf')) {
            $employees = Employee::query()
                ->select(['id', 'employee_number', 'name', 'department', 'level', 'job_title', 'roster', 'poh_status', 'poh_city', 'poh_province'])
                ->where('active', true)
                ->orderBy('name')
                ->limit(500)
                ->get();
        } else {
            $employees = Employee::query()
                ->select(['id', 'employee_number', 'name', 'department', 'level', 'job_title', 'roster', 'poh_status', 'poh_city', 'poh_province'])
                ->where('user_id', $request->user()?->getKey())
                ->where('active', true)
                ->get();
        }

        return [
            'leave_types' => config('eform.leave_types', []),
            'period_categories' => $periodOptions,
            'cost_categories' => $costOptions,
            'employees' => $employees,
        ];
    }

    protected function isDuplicateEntry(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? '');
        $message = strtolower($e->getMessage());

        return $code === '1062'
            || str_contains($message, 'duplicate entry');
    }
}
