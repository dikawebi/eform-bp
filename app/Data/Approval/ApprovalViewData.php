<?php

namespace App\Data\Approval;

use App\Models\ApprovalRequest;
use App\Models\LeaveRequest;
use App\Models\MedicalClaim;
use App\Models\Settlement;
use App\Models\TravelRequest;
use App\Models\User;

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
            } else {
                $summary = ['id' => $parent?->id, 'type' => $approval->approvable_type];
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

    private static function owner(MedicalClaim $claim, User $viewer): bool
    {
        return (int) $claim->created_by === (int) $viewer->id || (int) $claim->employee?->user_id === (int) $viewer->id;
    }
}
