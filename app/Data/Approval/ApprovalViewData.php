<?php

namespace App\Data\Approval;

use App\Models\ApprovalRequest;
use App\Models\ErpRequest;
use App\Models\ItItemOption;
use App\Models\ItRequest;
use App\Models\LeaveRequest;
use App\Models\MedicalClaim;
use App\Models\Settlement;
use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class ApprovalViewData
{
    public static function make(ApprovalRequest $approval, User $viewer): array
    {
        $medical = $approval->approvable instanceof MedicalClaim;
        $canSeeMedicalComments = ! $medical || self::owner($approval->approvable, $viewer) || ($viewer->can('medical.view.sensitive') && $viewer->can('medical.review'));
        $data = [
            'id' => $approval->id, 'approvable_type' => $approval->approvable_type, 'approvable_id' => $approval->approvable_id,
            'step_order' => $approval->step_order, 'step_code' => $approval->step_code, 'approver_role' => $approval->approver_role,
            'status' => $approval->status->value, 'due_at' => $approval->due_at?->toISOString(), 'acted_at' => $approval->acted_at?->toISOString(),
            'comments' => $canSeeMedicalComments ? $approval->comments : null, 'chain_generation' => $approval->chain_generation,
            'escalation' => self::escalationContext($approval),
            'actions' => $canSeeMedicalComments ? $approval->actions->map(fn ($action) => [
                // `action` is the canonical field. The UI already falls back to it
                // when `status` is absent; do not misrepresent an action as a status.
                'id' => $action->id, 'action' => $action->action->value,
                'actor' => $action->actor?->name, 'at' => $action->created_at?->toISOString(), 'comments' => $action->comments,
            ])->values()->all() : [],
        ];

        if (! $approval->approvable instanceof MedicalClaim) {
            $parent = $approval->approvable;
            if ($parent instanceof LeaveRequest) {
                $parent->loadMissing('employee:id,employee_number,name,department', 'periods');
                $summary = [
                    'id' => $parent->id, 'type' => 'leave', 'request_number' => $parent->request_number,
                    'status' => $parent->status->value, 'leave_type' => $parent->leave_type,
                    'reason' => $parent->reason, 'total_days' => $parent->total_days,
                    'total_advance' => $parent->total_advance,
                    'employee' => $parent->employee?->only(['employee_number', 'name', 'department']),
                    'periods' => $parent->periods->map(fn ($period) => ['category' => $period->category, 'start_date' => $period->start_date?->toDateString(), 'end_date' => $period->end_date?->toDateString(), 'day_count' => $period->day_count])->values()->all(),
                    'url' => route('leaves.show', $parent),
                ];
            } elseif ($parent instanceof TravelRequest) {
                $parent->loadMissing('employee:id,employee_number,name,department');
                $summary = [
                    'id' => $parent->id, 'type' => 'travel', 'request_number' => $parent->request_number,
                    'status' => $parent->status->value, 'purpose' => $parent->purpose,
                    'start_date' => $parent->start_date?->toDateString(), 'end_date' => $parent->end_date?->toDateString(),
                    'origin' => $parent->origin, 'destination' => $parent->destination,
                    'total_advance' => $parent->total_advance,
                    'employee' => $parent->employee?->only(['employee_number', 'name', 'department']),
                    'url' => route('travels.show', $parent),
                ];
            } elseif ($parent instanceof Settlement) {
                $parent->loadMissing('employee:id,employee_number,name,department');
                $summary = [
                    'id' => $parent->id, 'type' => 'settlement', 'request_number' => $parent->settlement_number,
                    'status' => $parent->status->value, 'source_type' => $parent->source_type,
                    'source_reference' => $parent->source_reference,
                    'advance_amount' => $parent->advance_amount, 'actual_amount' => $parent->actual_amount,
                    'difference_amount' => $parent->difference_amount, 'difference_type' => $parent->difference_type?->value,
                    'employee' => $parent->employee?->only(['employee_number', 'name', 'department']),
                    'url' => route('settlements.show', $parent),
                ];
            } elseif ($parent instanceof ItRequest) {
                $parent->loadMissing('employee:id,employee_number,name,department');
                $optionLabels = \App\Models\ItItemOption::query()->pluck('label', 'code')->all();
                $deviceLabel = $parent->device_type !== null && $parent->device_type !== ''
                    ? ($optionLabels[$parent->device_type] ?? $parent->device_type)
                    : null;
                $summary = [
                    'id' => $parent->id, 'type' => 'it_request', 'request_number' => $parent->request_number,
                    'status' => $parent->status->value, 'request_type' => $parent->request_type,
                    'device_type' => $parent->device_type, 'device_label' => $deviceLabel,
                    'priority' => $parent->priority,
                    'needed_date' => $parent->needed_date?->toDateString(),
                    'special_specification' => (bool) $parent->special_specification,
                    'description' => $parent->description, 'purpose' => $parent->purpose,
                    'replacement_reason' => $parent->replacement_reason, 'replacement_note' => $parent->replacement_note,
                    'accessories' => collect((array) ($parent->accessories_json ?? []))
                        ->map(fn ($code) => ['code' => $code, 'label' => $optionLabels[$code] ?? $code])->values()->all(),
                    'accessory_other_note' => $parent->accessory_other_note,
                    'software_standard' => array_values((array) ($parent->software_standard_json ?? [])),
                    'software_optional' => collect((array) ($parent->software_optional_json ?? []))
                        ->map(fn ($item) => ['name' => $item['name'] ?? $item, 'license' => $item['license'] ?? null])->values()->all(),
                    'recipient' => array_filter([
                        'name' => $parent->employee?->name ?? $parent->recipient_name,
                        'site' => $parent->recipient_site, 'cost_code' => $parent->recipient_cost_code,
                        'position' => $parent->recipient_position, 'is_new_employee' => (bool) $parent->is_new_employee,
                    ], fn ($value) => $value !== null && $value !== '' && $value !== false),
                    'employee' => $parent->employee?->only(['employee_number', 'name', 'department']),
                    'url' => route('it-requests.show', $parent),
                ];
            } elseif ($parent instanceof ErpRequest) {
                $parent->loadMissing('employee:id,employee_number,name,department');
                $summary = [
                    'id' => $parent->id, 'type' => 'erp_request', 'request_number' => $parent->request_number,
                    'status' => $parent->status->value, 'action_type' => $parent->action_type,
                    'employee' => $parent->employee?->only(['employee_number', 'name', 'department']),
                    'url' => route('erp-requests.show', $parent),
                ];
            } else {
                $summary = ['id' => $parent?->id, 'type' => $approval->approvable_type];
            }

            // Visualisasi form approval IT & ERP: rantai tahap untuk stepper
            // grafis + payload formulir lengkap untuk tab Formulir read-only.
            if ($parent instanceof ItRequest || $parent instanceof ErpRequest) {
                $summary['chain'] = self::approvalChain($parent);
                $summary['form'] = $parent instanceof ItRequest ? self::itForm($parent) : self::erpForm($parent);
                $summary['form_meta'] = $parent instanceof ItRequest ? self::itFormMeta() : self::erpFormMeta();
            }

            return $data + ['approvable' => $summary];
        }

        $claim = $approval->approvable;
        $claim->loadMissing('employee:id,user_id,employee_number,name,department', 'items', 'attachments');
        if ($viewer->can('medical.view.sensitive') && ($viewer->can('medical.review') || self::owner($claim, $viewer))) {
            $data['approvable'] = [
                'id' => $claim->id,
                'type' => 'medical_claim',
                'claim_number' => $claim->claim_number,
                'benefit_types' => $claim->benefit_types ?: [$claim->benefit_type],
                'status' => $claim->status->value,
                'employee' => $claim->employee?->only(['employee_number', 'name', 'department']),
                'total_amount' => $claim->total_amount,
                'items' => $claim->items->map(fn ($item) => [
                    'patient_name' => $item->patient_name,
                    'relationship' => $item->relationship,
                    'treatment_date' => $item->treatment_date?->toDateString(),
                    'facility_name' => $item->facility_name,
                    'amount' => $item->amount,
                ])->values()->all(),
                'attachments' => $claim->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'document_type' => $attachment->document_type,
                    'original_name' => $attachment->original_name,
                    'download_url' => route('attachments.download', $attachment),
                ])->values()->all(),
            ];
        } elseif ($viewer->can('medical.payment.process') || $viewer->can('medical.payment.complete') || $viewer->can('medical.view.aggregate')) {
            $data['approvable'] = ['id' => $claim->id, 'type' => 'medical_claim', 'claim_number' => $claim->claim_number, 'status' => $claim->status->value];
        } else {
            $data['approvable'] = ['id' => $claim->id, 'type' => 'medical_claim', 'status' => $claim->status->value];
        }

        return $data;
    }

    /**
     * Tahap rantai generasi aktif untuk stepper grafis (urut step_order).
     *
     * @return list<array{step_code: string, status: string, approver: string|null, acted_at: string|null}>
     */
    private static function approvalChain(Model $parent): array
    {
        $generation = $parent->approvalRequests()->orderByDesc('id')->value('chain_generation');
        if ($generation === null) {
            return [];
        }
        $parent->loadMissing('approvalRequests.approver:id,name');

        return $parent->approvalRequests->where('chain_generation', $generation)->sortBy('step_order')
            ->map(fn (ApprovalRequest $item) => [
                'step_code' => $item->step_code, 'status' => $item->status->value,
                'approver' => $item->approver?->name, 'acted_at' => $item->acted_at?->toISOString(),
            ])->values()->all();
    }

    /**
     * DTO formulir IT lengkap (cermin ItRequestController::dto) untuk
     * tab Formulir read-only di halaman approval.
     */
    private static function itForm(ItRequest $it): array
    {
        $it->loadMissing('employee:id,employee_number,name,department,job_title');

        return [
            'id' => $it->id, 'request_number' => $it->request_number, 'status' => $it->status->value,
            'employee_id' => $it->employee_id,
            'employee' => $it->employee?->only(['id', 'employee_number', 'name', 'department', 'job_title']),
            'is_new_employee' => (bool) $it->is_new_employee,
            'recipient_name' => $it->recipient_name, 'recipient_id_number' => $it->recipient_id_number,
            'recipient_site' => $it->recipient_site, 'recipient_cost_code' => $it->recipient_cost_code,
            'recipient_position' => $it->recipient_position,
            'recipient_effective_date' => $it->recipient_effective_date?->toDateString(),
            'request_type' => $it->request_type, 'replacement_reason' => $it->replacement_reason,
            'replacement_note' => $it->replacement_note, 'device_type' => $it->device_type,
            'special_specification' => (bool) $it->special_specification,
            'description' => $it->description, 'purpose' => $it->purpose,
            'software_standard' => $it->software_standard_json ?? [],
            'software_optional' => $it->software_optional_json ?? [],
            'accessories' => $it->accessories_json ?? [],
            'accessory_other_note' => $it->accessory_other_note,
            'needed_date' => $it->needed_date?->toDateString(), 'priority' => $it->priority,
        ];
    }

    private static function itFormMeta(): array
    {
        $options = fn (string $kind) => ItItemOption::query()->where('kind', $kind)->active()->ordered()->get()
            ->map(fn (ItItemOption $option) => ['value' => $option->code, 'label' => $option->label, 'requires_note' => (bool) $option->requires_note])
            ->values()->all();

        return [
            'devices' => config('eform.it_request.devices', []),
            'priorities' => config('eform.it_request.priorities', []),
            'software_standard' => config('eform.it_request.software_standard', []),
            'replacement_reasons' => config('eform.it_request.replacement_reasons', []),
            'device_options' => $options('device'),
            'accessory_options' => $options('accessory'),
            'option_labels' => ItItemOption::query()->pluck('label', 'code')->all(),
            'allowed_documents' => config('eform.it_request.allowed_documents', []),
            'employees' => [],
        ];
    }

    /**
     * DTO formulir ERP lengkap (cermin ErpRequestController::dto).
     */
    private static function erpForm(ErpRequest $erp): array
    {
        $erp->loadMissing('employee:id,employee_number,name,department,job_title');

        return [
            'id' => $erp->id, 'request_number' => $erp->request_number, 'status' => $erp->status->value,
            'employee_id' => $erp->employee_id,
            'employee' => $erp->employee?->only(['id', 'employee_number', 'name', 'department', 'job_title']),
            'recipient_name' => $erp->recipient_name, 'recipient_site' => $erp->recipient_site,
            'recipient_cost_code' => $erp->recipient_cost_code, 'recipient_position' => $erp->recipient_position,
            'action_type' => $erp->action_type, 'existing_erp_username' => $erp->existing_erp_username,
            'business_purpose' => $erp->business_purpose, 'modules' => $erp->modules_json ?? [],
        ];
    }

    private static function erpFormMeta(): array
    {
        return [
            'action_types' => config('eform.erp_request.action_types', []),
            'modules' => config('eform.erp_request.modules', []),
            'allowed_documents' => config('eform.erp_request.allowed_documents', []),
            'employees' => [],
        ];
    }

    /**
     * Konteks eskalasi untuk tahap COO/CEO: catatan dari IT, siapa yang
     * mengeskalasi, dan role tujuan (coo/ceo) agar penerima tidak perlu
     * menelusuri dokumen.
     *
     * @return array<string, mixed>|null
     */
    private static function escalationContext(ApprovalRequest $approval): ?array
    {
        if ($approval->step_code !== 'coo_ceo') {
            return null;
        }
        $parent = $approval->approvable;
        if (! $parent instanceof ItRequest) {
            return null;
        }
        $snapshot = (array) ($parent->employee_snapshot_json['approval_snapshot']['coo_ceo'] ?? []);
        if (($snapshot['decision'] ?? null) === 'none') {
            return null;
        }
        $note = trim((string) ($snapshot['escalation_note'] ?? ''));
        if ($note === '' && trim((string) $approval->comments) === '') {
            return null;
        }

        return [
            'role' => $snapshot['role'] ?? $approval->approver_role,
            'note' => $note !== '' ? $note : $approval->comments,
            'decided_by' => isset($snapshot['decided_by']) ? User::query()->whereKey($snapshot['decided_by'])->value('name') : null,
            'special_specification' => (bool) $parent->special_specification,
            'description' => $parent->description,
            'purpose' => $parent->purpose,
        ];
    }

    private static function owner(MedicalClaim $claim, User $viewer): bool
    {
        return (int) $claim->created_by === (int) $viewer->id || (int) $claim->employee?->user_id === (int) $viewer->id;
    }
}
