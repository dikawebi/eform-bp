<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Http\Requests\CompleteMedicalPaymentRequest;
use App\Http\Requests\StoreMedicalClaimRequest;
use App\Http\Requests\UpdateMedicalClaimRequest;
use App\Http\Requests\UploadMedicalAttachmentRequest;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\MedicalClaim;
use App\Models\MedicalDependent;
use App\Services\Medical\CalculateMedicalClaim;
use App\Services\Medical\MedicalClaimNumber;
use App\Services\Medical\SubmitMedicalClaim;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class MedicalClaimController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', MedicalClaim::class);
        $user = $request->user();
        $query = MedicalClaim::with('employee:id,employee_number,name,department')->latest();
        if (! $user->can('medical.view.all') && ! $user->can('medical.view.aggregate')) {
            $query->whereIn('employee_id', Employee::where('user_id', $user->id)->pluck('id'));
        }
        $page = $query->paginate(15)->withQueryString();
        $page->setCollection($page->getCollection()->map(function (MedicalClaim $claim) use ($user) {
            $owner = $user->can('viewOwn', $claim);
            $detail = $owner || $user->can('medical.view.sensitive');
            $aggregate = $user->can('medical.view.aggregate') && ! $user->hasRole('auditor');
            $benefitTypes = $claim->benefit_types ?: [$claim->benefit_type];

            return ['id' => $claim->id, 'claim_number' => $claim->claim_number, 'status' => $claim->status->value, 'total_amount' => ($owner || $aggregate || $user->can('medical.view.all')) ? $claim->total_amount : null, 'benefit_type' => $detail ? $claim->benefit_type : 'medical_claim', 'benefit_types' => $detail ? $benefitTypes : [], 'employee' => $detail ? $claim->employee?->only(['id', 'employee_number', 'name', 'department']) : null];
        }));

        return Inertia::render('MedicalClaims/Index', ['claims' => $page, 'statuses' => RequestStatus::values(), 'canCreate' => $user->can('create', MedicalClaim::class)]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', MedicalClaim::class);

        $employee = Employee::query()->where('user_id', $request->user()->id)->where('active', true)->first();
        if ($employee === null) {
            return Inertia::render('MedicalClaims/Unlinked', [
                'canManageEmployees' => $request->user()->can('employee.manage'),
            ]);
        }

        return Inertia::render('MedicalClaims/Create', ['meta' => ['benefit_types' => config('eform.medical.benefit_types'), 'required_documents' => config('eform.medical.required_documents'), 'dependents' => $employee->medicalDependents()->where('active', true)->get(['id', 'name', 'relationship'])]]);
    }

    public function store(StoreMedicalClaimRequest $request): RedirectResponse
    {
        $this->authorize('create', MedicalClaim::class);
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->where('active', true)->firstOrFail();
        $claim = DB::transaction(function () use ($request, $user, $employee) {
            $benefitTypes = $request->validated('benefit_types');
            $claim = new MedicalClaim(['benefit_type' => $benefitTypes[0]]);
            $claim->forceFill(['benefit_types' => $benefitTypes, 'claim_number' => MedicalClaimNumber::generate(), 'employee_id' => $employee->id, 'employee_number' => $employee->employee_number, 'employee_name' => $employee->name, 'department' => $employee->department, 'employee_snapshot_json' => $employee->only(['id', 'employee_number', 'name', 'department', 'level', 'job_title', 'roster', 'poh_status']), 'status' => RequestStatus::Draft, 'created_by' => $user->id, 'updated_by' => $user->id])->save();
            $this->syncItems($claim, $request->validated('items'));
            activity()->performedOn($claim)->causedBy($user)->log('medical.created');

            return $claim;
        });

        return redirect()->route('medical-claims.show', $claim)->with('success', 'Medical claim draft berhasil dibuat.');
    }

    public function show(Request $request, MedicalClaim $medical_claim): Response
    {
        $this->authorize('view', $medical_claim);
        $medical_claim->load(['employee:id,employee_number,name,department', 'items', 'attachments', 'approvalRequests.approver:id,name']);
        $user = $request->user();
        $sensitive = $user->can('viewSensitive', $medical_claim);
        $aggregate = $user->can('viewAggregate', $medical_claim) && ! $user->hasRole('auditor');
        $owner = $user->can('viewOwn', $medical_claim);
        $detail = $sensitive || $owner;
        $dto = ['id' => $medical_claim->id, 'claim_number' => $medical_claim->claim_number, 'benefit_type' => $detail ? $medical_claim->benefit_type : 'medical_claim', 'benefit_types' => $detail ? ($medical_claim->benefit_types ?: [$medical_claim->benefit_type]) : [], 'status' => $medical_claim->status->value, 'total_amount' => ($aggregate || $owner || $sensitive) ? $medical_claim->total_amount : null, 'employee' => ($detail && $medical_claim->employee) ? $medical_claim->employee->only(['employee_number', 'name', 'department']) : null, 'items' => $detail ? $medical_claim->items->map(fn ($item) => ['id' => $item->id, 'patient_name' => $item->patient_name, 'relationship' => $item->relationship, 'dependent_id' => $item->dependent_id, 'treatment_date' => $item->treatment_date?->toDateString(), 'facility_name' => $item->facility_name, 'amount' => $item->amount])->values()->all() : [], 'attachments' => $detail ? $medical_claim->attachments->map(fn (Attachment $a) => ['id' => $a->id, 'document_type' => $a->document_type, 'original_name' => $a->original_name, 'download_url' => route('attachments.download', $a)])->values()->all() : []];
        $timeline = $medical_claim->approvalRequests->sortBy('step_order')->map(fn ($a) => array_filter(['step_code' => $a->step_code, 'status' => $a->status->value, 'actor' => $detail ? $a->approver?->name : null, 'at' => $a->acted_at?->toISOString(), 'comments' => $detail ? $a->comments : null], fn ($value) => $value !== null))->values()->all();
        $activities = $detail ? Activity::where('subject_type', $medical_claim->getMorphClass())->where('subject_id', $medical_claim->id)->latest()->limit(50)->get()->map(fn ($a) => ['id' => $a->id, 'action' => $a->description, 'actor' => $a->causer?->name, 'at' => $a->created_at?->toISOString()])->values()->all() : [];

        return Inertia::render('MedicalClaims/Show', ['claim' => $dto, 'timeline' => $timeline, 'activities' => $activities, 'availableActions' => ['can_edit' => $user->can('update', $medical_claim), 'can_submit' => $user->can('submit', $medical_claim), 'can_cancel' => $user->can('cancel', $medical_claim), 'can_upload' => $user->can('upload', $medical_claim), 'can_payment_process' => $user->can('payment', $medical_claim), 'can_payment_complete' => $user->can('complete', $medical_claim)], 'can_view_sensitive' => $sensitive]);
    }

    public function edit(Request $request, MedicalClaim $medical_claim): Response
    {
        $this->authorize('update', $medical_claim);
        $medical_claim->load('items');

        $employee = Employee::where('user_id', $request->user()->id)->first();

        $canSeeAmount = $request->user()->can('viewSensitive', $medical_claim) || $request->user()->can('viewOwn', $medical_claim);
        $canSeeDiagnosis = $request->user()->can('viewSensitive', $medical_claim);
        $items = $medical_claim->items->map(function ($item) use ($canSeeAmount, $canSeeDiagnosis) {
            $dto = [
                'id' => $item->id,
                'patient_name' => $item->patient_name,
                'dependent_id' => $item->dependent_id,
                'relationship' => $item->relationship,
                'treatment_date' => $item->treatment_date?->toDateString(),
                'facility_name' => $item->facility_name,
            ];
            if ($canSeeAmount) {
                $dto['amount'] = $item->amount;
            }
            if ($canSeeDiagnosis) {
                $dto['diagnosis_code'] = $item->diagnosis_code;
            }

            return $dto;
        })->values()->all();

        $benefitTypes = (array) config('eform.medical.benefit_types', []);
        if (! in_array($medical_claim->benefit_type, $benefitTypes, true)) {
            $benefitTypes[] = $medical_claim->benefit_type;
        }

        return Inertia::render('MedicalClaims/Edit', ['claim' => ['id' => $medical_claim->id, 'benefit_type' => $medical_claim->benefit_type, 'benefit_types' => $medical_claim->benefit_types ?: [$medical_claim->benefit_type], 'items' => $items], 'meta' => ['benefit_types' => $benefitTypes, 'dependents' => $employee?->medicalDependents()->where('active', true)->get(['id', 'name', 'relationship']) ?? []]]);
    }

    public function update(UpdateMedicalClaimRequest $request, MedicalClaim $medical_claim): RedirectResponse
    {
        $this->authorize('update', $medical_claim);
        try {
            DB::transaction(function () use ($request, $medical_claim) {
                $claim = MedicalClaim::lockForUpdate()->findOrFail($medical_claim->id);
                $this->authorize('update', $claim);
                $benefitTypes = $request->validated('benefit_types');
                $claim->forceFill(['benefit_type' => $benefitTypes[0], 'benefit_types' => $benefitTypes, 'updated_by' => $request->user()->id])->save();
                $this->syncItems($claim, $request->validated('items'));
                activity()->performedOn($claim)->causedBy($request->user())->log('medical.updated');
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicatePaymentReference($exception)) {
                throw ValidationException::withMessages(['payment_reference' => 'Referensi pembayaran sudah digunakan.']);
            }
            throw $exception;
        }

        return redirect()->route('medical-claims.show', $medical_claim)->with('success', 'Medical claim diperbarui.');
    }

    public function submit(Request $request, MedicalClaim $medical_claim): RedirectResponse
    {
        $this->authorize('submit', $medical_claim);
        SubmitMedicalClaim::run($medical_claim, $request->user());

        return back()->with('success', 'Medical claim berhasil diajukan.');
    }

    public function cancel(Request $request, MedicalClaim $medical_claim): RedirectResponse
    {
        $this->authorize('cancel', $medical_claim);
        DB::transaction(function () use ($request, $medical_claim) {
            $claim = MedicalClaim::lockForUpdate()->findOrFail($medical_claim->id);
            $this->authorize('cancel', $claim);
            $from = $claim->status;
            $claim->forceFill(['status' => RequestStatus::Cancelled, 'updated_by' => $request->user()->id])->save();
            activity()->performedOn($claim)->causedBy($request->user())->withProperties(['from' => $from->value, 'to' => 'cancelled'])->log('medical.cancelled');
        });

        return back()->with('success', 'Medical claim dibatalkan.');
    }

    public function complete(CompleteMedicalPaymentRequest $request, MedicalClaim $medical_claim): RedirectResponse
    {
        $this->authorize('complete', $medical_claim);
        DB::transaction(function () use ($request, $medical_claim) {
            $claim = MedicalClaim::lockForUpdate()->findOrFail($medical_claim->id);
            if ($claim->status !== RequestStatus::PaymentProcessing) {
                throw ValidationException::withMessages(['status' => 'Claim belum berada pada proses pembayaran.']);
            } $claim->forceFill(['status' => RequestStatus::Completed, 'completed_at' => now(), 'payment_reference' => $request->validated('payment_reference'), 'payment_date' => $request->validated('payment_date'), 'updated_by' => $request->user()->id])->save();
            activity()->performedOn($claim)->causedBy($request->user())->withProperties(['payment_reference_hash' => hash('sha256', (string) $claim->payment_reference), 'payment_date' => $claim->payment_date?->toDateString(), 'actor_id' => $request->user()->id, 'at' => now()->toISOString()])->log('medical.completed');
        });

        return back()->with('success', 'Medical claim selesai diproses.');
    }

    public function payment(Request $request, MedicalClaim $medical_claim): RedirectResponse
    {
        $this->authorize('payment', $medical_claim);
        DB::transaction(function () use ($request, $medical_claim): void {
            $claim = MedicalClaim::query()->lockForUpdate()->findOrFail($medical_claim->id);
            if ($claim->status !== RequestStatus::Approved) {
                throw ValidationException::withMessages(['status' => 'Medical claim belum disetujui untuk pembayaran.']);
            }
            $claim->forceFill(['status' => RequestStatus::PaymentProcessing, 'updated_by' => $request->user()->id])->save();
            $claim->forceFill(['payment_processed_by' => $request->user()->id, 'payment_processed_at' => now()])->save();
            activity()->performedOn($claim)->causedBy($request->user())->withProperties(['from' => RequestStatus::Approved->value, 'to' => RequestStatus::PaymentProcessing->value, 'actor_id' => $request->user()->id, 'at' => now()->toISOString()])->log('medical.payment_processing');
        });

        return back()->with('success', 'Medical claim masuk proses pembayaran.');
    }

    public function uploadAttachment(UploadMedicalAttachmentRequest $request, MedicalClaim $medical_claim): RedirectResponse
    {
        $this->authorize('upload', $medical_claim);
        $file = $request->file('file');
        $path = $file->store('medical/'.$medical_claim->id, 'eform-private');
        try {
            DB::transaction(function () use ($request, $medical_claim, $file, $path) {
                $claim = MedicalClaim::lockForUpdate()->findOrFail($medical_claim->id);
                $this->authorize('upload', $claim);
                $attachment = $claim->attachments()->create(['document_type' => $request->validated('document_type'), 'original_name' => $file->getClientOriginalName(), 'stored_path' => $path, 'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'sha256_hash' => hash_file('sha256', $file->getRealPath()), 'uploaded_by' => $request->user()->id]);
                activity()->performedOn($claim)->causedBy($request->user())->withProperties(['attachment_id' => $attachment->id, 'document_type' => $attachment->document_type])->log('medical.attachment_uploaded');
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

    private function syncItems(MedicalClaim $claim, array $items): void
    {
        $claim->items()->delete();
        foreach ($items as $row) {
            $patientName = $row['relationship'] === 'self' ? $claim->employee_name : MedicalDependent::query()->where('employee_id', $claim->employee_id)->whereKey($row['dependent_id'])->where('relationship', $row['relationship'])->where('active', true)->firstOrFail()->name;
            $claim->items()->create(['patient_name' => $patientName, 'relationship' => $row['relationship'], 'dependent_id' => $row['relationship'] === 'self' ? null : $row['dependent_id'], 'treatment_date' => $row['treatment_date'], 'facility_name' => $row['facility_name'], 'diagnosis_code' => $row['diagnosis_code'] ?? null, 'amount' => $row['amount']]);
        } CalculateMedicalClaim::run($claim->load('items'));
    }

    private function isDuplicatePaymentReference(QueryException $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), 'payment_reference')
            || str_contains(strtolower($exception->getMessage()), 'medical_claims_payment_reference_unique');
    }
}
