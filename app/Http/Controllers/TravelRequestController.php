<?php

namespace App\Http\Controllers;

use App\Enums\CostCategory;
use App\Enums\RequestStatus;
use App\Http\Requests\ProcessAdvanceRequest;
use App\Http\Requests\StoreTravelRequestRequest;
use App\Http\Requests\UpdateTravelRequestRequest;
use App\Http\Requests\UploadTravelAttachmentRequest;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\TravelRequest;
use App\Services\AdvanceProcessing;
use App\Services\EmployeeVisibility;
use App\Services\Travel\CalculateTravelAdvance;
use App\Services\Approval\ApprovalActionAvailability;
use App\Services\Travel\SubmitTravelRequest;
use App\Services\Settlement\SettlementSourceAvailability;
use App\Services\Travel\TravelRequestNumber;
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
 * Perjalanan Dinas Phase 3 (PRD §2 modul B, §5.2, §6, §7).
 *
 * - Validasi via Form Request; authorization via TravelRequestPolicy.
 * - Total + transition server-side dalam DB transaction + activity log.
 * - Tanpa hapus fisik (tidak ada destroy).
 * - Lampiran formal menggunakan storage private dan policy parent di Phase 5.
 */
class TravelRequestController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TravelRequest::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = (string) ($validated['status'] ?? '');

        $user = $request->user();

        $travels = TravelRequest::query()
            ->with(['employee:id,employee_number,name,department'])
            ->when(! app(EmployeeVisibility::class)->hasBroadAccess($user), fn ($query) => app(EmployeeVisibility::class)->scope($user, $query))
            ->when($search !== '', function ($query) use ($search) {
                $like = "%{$search}%";
                $query->where(function ($inner) use ($like) {
                    $inner->where('request_number', 'like', $like)
                        ->orWhere('purpose', 'like', $like)
                        ->orWhere('employee_name', 'like', $like);
                });
            })
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Travels/Index', [
            'travels' => $travels,
            'filters' => ['search' => $search, 'status' => $status],
            'statuses' => RequestStatus::values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', TravelRequest::class);

        $linked = Employee::query()->where('user_id', $request->user()->id)->where('active', true)->exists();
        if (! $linked && ! $request->user()->can('travel.create.onbehalf')) {
            return Inertia::render('Travels/Unlinked', [
                'canManageEmployees' => $request->user()->can('employee.manage'),
            ]);
        }

        return Inertia::render('Travels/Create', [
            'meta' => $this->formMeta($request),
        ]);
    }

    public function store(StoreTravelRequestRequest $request): RedirectResponse
    {
        $this->authorize('create', TravelRequest::class);

        $validated = $request->validated();
        $user = $request->user();

        // Retry untuk race nomor unik (DINAS-YYYYMM-0001 atomik).
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $travel = DB::transaction(function () use ($validated, $user) {
                    $employee = $this->resolveEmployee($user, isset($validated['employee_id']) ? (int) $validated['employee_id'] : null);

                    $travel = new TravelRequest(Arr::only($validated, [
                        'purpose', 'start_date', 'end_date', 'origin', 'destination',
                    ]));
                    $travel->is_project_trip = $user->can('travel.project.flag') && (bool) ($validated['is_project_trip'] ?? false);
                    $travel->request_number = TravelRequestNumber::generate();
                    $travel->employee_id = $employee->getKey();
                    $travel->status = RequestStatus::Draft;
                    $travel->total_advance = 0;
                    $travel->created_by = $user->getKey();
                    $travel->updated_by = $user->getKey();
                    $travel->save();

                    $this->syncItems($travel, $validated['items'] ?? []);

                    $fresh = $travel->refresh();

                    // Audit di dalam transaction agar atomik dengan data.
                    activity()
                        ->performedOn($fresh)
                        ->causedBy($user)
                        ->withProperties([
                            'request_number' => $fresh->request_number,
                            'total_advance' => (float) $fresh->total_advance,
                        ])
                        ->log('travel.created');

                    return $fresh;
                });

                return redirect()
                    ->route('travels.show', $travel)
                    ->with('success', 'Pengajuan perjalanan dinas berhasil dibuat sebagai draft.');
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

    public function show(Request $request, TravelRequest $travel): Response
    {
        $this->authorize('view', $travel);

        $travel->load(['employee:id,employee_number,name,department,level,job_title,roster,poh_status', 'items', 'attachments', 'approvalRequests.approver:id,name', 'approvalRequests.actions.actor:id,name']);

        $attachments = $travel->attachments->map(fn (Attachment $attachment) => [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'document_type' => $attachment->document_type,
            'mime_type' => $attachment->mime_type,
            'file_size' => $attachment->file_size,
            'download_url' => route('attachments.download', $attachment),
        ])->values()->all();

        $activities = Activity::query()
            ->where('subject_type', $travel->getMorphClass())
            ->where('subject_id', $travel->getKey())
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $travelDto = [
            'id' => $travel->id,
            'request_number' => $travel->request_number,
            'employee_id' => $travel->employee_id,
            'purpose' => $travel->purpose,
            'start_date' => $travel->start_date?->toDateString(),
            'end_date' => $travel->end_date?->toDateString(),
            'origin' => $travel->origin,
            'destination' => $travel->destination,
            'is_project_trip' => (bool) $travel->is_project_trip,
            'total_advance' => $travel->total_advance,
            'status' => $travel->status->value,
            'submitted_at' => $travel->submitted_at?->toISOString(),
            'approved_at' => $travel->approved_at?->toISOString(),
            'completed_at' => $travel->completed_at?->toISOString(),
            'employee' => $travel->employee?->only(['id', 'employee_number', 'name', 'department', 'level', 'job_title', 'roster', 'poh_status']),
            'items' => $travel->items->map(fn ($item) => [
                'id' => $item->id, 'category' => $item->category, 'transaction_date' => $item->transaction_date?->toDateString(),
                'origin' => $item->origin, 'destination' => $item->destination, 'description' => $item->description,
                'quantity' => $item->quantity, 'unit_price' => $item->unit_price, 'amount' => $item->amount, 'metadata_json' => $item->metadata_json,
            ])->values()->all(),
            'attachments' => $attachments,
        ];

        $timeline = $travel->approvalRequests->sortBy('step_order')->map(fn ($approval) => [
            'id' => $approval->id, 'step_order' => $approval->step_order, 'step_code' => $approval->step_code,
            'approver_role' => $approval->approver_role, 'status' => $approval->status->value,
            'approver' => $approval->approver ? ['id' => $approval->approver->id, 'name' => $approval->approver->name] : null,
            'acted_by' => $approval->actions->sortByDesc('id')->first()?->actor ? ['id' => $approval->actions->sortByDesc('id')->first()->actor->id, 'name' => $approval->actions->sortByDesc('id')->first()->actor->name] : null,
            'due_at' => $approval->due_at?->toISOString(), 'acted_at' => $approval->acted_at?->toISOString(), 'comments' => $approval->comments,
        ])->values()->all();

        return Inertia::render('Travels/Show', [
            'travel' => $travelDto,
            'requires_settlement' => $travel->needsSettlement(),
            'activities' => $activities->map(fn (Activity $activity) => [
                'id' => $activity->id, 'description' => $activity->description, 'created_at' => $activity->created_at?->toISOString(),
                'actor' => $activity->causer ? ['id' => $activity->causer->id, 'name' => $activity->causer->name] : null,
            ])->values()->all(),
            'employee_snapshot' => $travel->status !== RequestStatus::Draft ? $travel->employee_snapshot_json : null,
            'availableActions' => [
                'can_edit' => (bool) $request->user()->can('update', $travel),
                'can_submit' => (bool) $request->user()->can('submit', $travel),
                'can_cancel' => (bool) $request->user()->can('cancel', $travel),
                'can_upload' => (bool) $request->user()->can('upload', $travel),
                'can_process_advance' => (bool) $request->user()->can('processAdvance', $travel),
            ],
            'approvalActions' => ApprovalActionAvailability::for($travel, $request->user()),
            'canCreateSettlement' => SettlementSourceAvailability::for($travel, $request->user()),
            'timeline' => $timeline,
        ]);
    }

    public function edit(Request $request, TravelRequest $travel): Response
    {
        $this->authorize('update', $travel);

        $travel->load(['employee:id,employee_number,name,department,poh_status', 'items']);

        return Inertia::render('Travels/Edit', [
            'travel' => [
                'id' => $travel->id, 'employee_id' => $travel->employee_id, 'purpose' => $travel->purpose,
                'start_date' => $travel->start_date?->toDateString(), 'end_date' => $travel->end_date?->toDateString(),
                'origin' => $travel->origin, 'destination' => $travel->destination, 'is_project_trip' => (bool) $travel->is_project_trip,
                'items' => $travel->items->map(fn ($item) => ['id' => $item->id, 'category' => $item->category, 'transaction_date' => $item->transaction_date?->toDateString(), 'origin' => $item->origin, 'destination' => $item->destination, 'description' => $item->description, 'quantity' => $item->quantity, 'unit_price' => $item->unit_price, 'metadata' => $item->metadata_json])->values()->all(),
            ],
            'meta' => $this->formMeta($request),
        ]);
    }

    public function update(UpdateTravelRequestRequest $request, TravelRequest $travel): RedirectResponse
    {
        // Policy update = owner + draft/returned (approved immutable → 403).
        $this->authorize('update', $travel);

        $validated = $request->validated();
        $user = $request->user();

        // Cegah TOCTOU — kunci baris + cek ulang status + authorize ulang
        // di dalam transaction sebelum syncItems.
        DB::transaction(function () use ($validated, $user, $travel) {
            $locked = TravelRequest::query()
                ->whereKey($travel->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, [RequestStatus::Draft, RequestStatus::Returned], true)) {
                abort(403, 'Pengajuan pada status ini tidak dapat diubah.');
            }

            $this->authorize('update', $locked);

            $locked->fill(Arr::only($validated, [
                'purpose', 'start_date', 'end_date', 'origin', 'destination',
            ]));
            $locked->is_project_trip = $user->can('travel.project.flag') && (bool) ($validated['is_project_trip'] ?? false);
            $locked->updated_by = $user->getKey();
            $locked->save();

            $this->syncItems($locked, $validated['items'] ?? []);

            $locked->refresh();

            // Audit di dalam transaction agar atomik dengan data.
            activity()
                ->performedOn($locked)
                ->causedBy($user)
                ->withProperties([
                    'request_number' => $locked->request_number,
                    'total_advance' => (float) $locked->total_advance,
                ])
                ->log('travel.updated');
        });

        return redirect()
            ->route('travels.show', $travel)
            ->with('success', 'Pengajuan perjalanan dinas berhasil diperbarui.');
    }

    /**
     * Submit draft/returned → submitted + snapshot (PRD §6.2-6.3).
     */
    public function submit(Request $request, TravelRequest $travel): RedirectResponse
    {
        $this->authorize('submit', $travel);

        try {
            SubmitTravelRequest::run($travel, $request->user());
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()
            ->route('travels.show', $travel)
            ->with('success', 'Pengajuan perjalanan dinas berhasil disubmit.');
    }

    public function processAdvance(ProcessAdvanceRequest $request, TravelRequest $travel): RedirectResponse
    {
        AdvanceProcessing::run($travel, $request->user());

        return back()->with('success', 'Advance perjalanan berhasil diproses.');
    }

    /**
     * Cancel draft/submitted/returned → cancelled (tanpa hapus fisik).
     */
    public function cancel(Request $request, TravelRequest $travel): RedirectResponse
    {
        $this->authorize('cancel', $travel);

        $user = $request->user();

        DB::transaction(function () use ($user, $travel) {
            $travel = TravelRequest::query()->whereKey($travel->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize('cancel', $travel);

            if (! in_array($travel->status, [RequestStatus::Draft, RequestStatus::Submitted, RequestStatus::Returned], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Pengajuan pada status ini tidak dapat dibatalkan.',
                ]);
            }

            $from = $travel->status;

            $travel->forceFill([
                'status' => RequestStatus::Cancelled,
                'updated_by' => $user->getKey(),
            ])->save();

            activity()
                ->performedOn($travel)
                ->causedBy($user)
                ->withProperties([
                    'request_number' => $travel->request_number,
                    'from' => $from->value,
                    'to' => RequestStatus::Cancelled->value,
                ])
                ->log('travel.cancelled');
        });

        return redirect()
            ->route('travels.show', $travel)
            ->with('success', 'Pengajuan perjalanan dinas dibatalkan.');
    }

    public function uploadAttachment(UploadTravelAttachmentRequest $request, TravelRequest $travel): RedirectResponse
    {
        $this->authorize('upload', $travel);
        $file = $request->file('file');
        $path = $file->store('travel/'.$travel->getKey(), 'eform-private');
        $hash = hash_file('sha256', $file->getRealPath());
        try {
            DB::transaction(function () use ($request, $travel, $file, $path, $hash): void {
                $locked = TravelRequest::query()->whereKey($travel->getKey())->with('employee')->lockForUpdate()->firstOrFail();
                $this->authorize('upload', $locked);
                $attachment = $locked->attachments()->create([
                    'document_type' => $request->validated('document_type'),
                    'original_name' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'sha256_hash' => $hash,
                    'uploaded_by' => $request->user()->id,
                ]);
                activity()->performedOn($locked)->causedBy($request->user())->withProperties([
                    'actor_id' => $request->user()->id, 'attachment_id' => $attachment->id,
                    'type' => $attachment->document_type, 'size' => $attachment->file_size, 'hash' => $hash,
                ])->log('travel.attachment_uploaded');
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
     * Resolve employee pemilik pengajuan. employee_id dari frontend tidak
     * dipercaya: untuk karyawan lain wajib travel.create.onbehalf
     * (admin + hrga_manager saja). travel.view.all TIDAK cukup (PRD §11).
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
            if (! $user->can('travel.create.onbehalf')) {
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
     * Ganti seluruh item biaya lalu hitung amount + total server-side.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function syncItems(TravelRequest $travel, array $items): void
    {
        $travel->items()->delete();

        // Akumulasi string (bcadd) agar presisi, bukan float mentah.
        $totalStr = '0';

        foreach ($items as $row) {
            $metadata = $row['metadata'] ?? [];
            $quantity = $row['quantity'] ?? 0;
            // Tiket pada formulir sumber hanya mencatat detail perjalanan;
            // nominalnya tidak menjadi komponen advance.
            $unitPrice = ($row['category'] ?? null) === CostCategory::Flight->value
                ? 0
                : ($row['unit_price'] ?? 0);
            if (($row['category'] ?? null) === 'hotel' && filled($metadata['check_in_date'] ?? null) && filled($metadata['check_out_date'] ?? null)) {
                $quantity = CarbonImmutable::parse($metadata['check_in_date'])->diffInDays(CarbonImmutable::parse($metadata['check_out_date']));
                $metadata['nights'] = (string) $quantity;
            }
            $amount = CalculateTravelAdvance::amountForItem(
                $quantity,
                $unitPrice,
            );

            // amount server-side: di luar fillable → forceFill.
            $item = $travel->items()->create([
                'category' => $row['category'],
                'transaction_date' => $row['transaction_date'] ?? null,
                'origin' => $row['origin'] ?? null,
                'destination' => $row['destination'] ?? null,
                'description' => $row['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ]);
            $item->forceFill([
                'amount' => $amount,
                'metadata_json' => $metadata ?: null,
            ])->save();

            $totalStr = bcadd($totalStr, $amount, 2);
            if (bccomp($totalStr, '999999999999.99', 2) === 1) {
                throw ValidationException::withMessages(['items' => 'Total biaya melebihi batas maksimum.']);
            }
        }

        $travel->forceFill(['total_advance' => $totalStr])->save();
    }

    /**
     * Meta opsi form dari enum (tidak hard-code di controller).
     *
     * @return array<string, mixed>
     */
    protected function formMeta(Request $request): array
    {
        $costOptions = [];
        foreach (CostCategory::cases() as $case) {
            $costOptions[] = ['value' => $case->value, 'label' => $case->label()];
        }

        $employees = [];
        // Daftar karyawan lain hanya untuk pemegang travel.create.onbehalf
        // (admin + hrga_manager). travel.view.all TIDAK cukup.
        if ($request->user()?->can('travel.create.onbehalf')) {
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
