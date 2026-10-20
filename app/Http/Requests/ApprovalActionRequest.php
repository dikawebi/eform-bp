<?php

namespace App\Http\Requests;

use App\Models\ItRequest;
use App\Models\User;
use App\Services\Approval\ApprovalActorConflicts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovalActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('act', $this->route('approval'));
    }

    public function rules(): array
    {
        $action = (string) $this->route('action');

        return [
            // Catatan wajib saat reject/return, dan wajib saat mengeskalasi
            // agar COO/CEO langsung mendapat konteks tanpa mencari lama.
            'comments' => [in_array($action, ['reject', 'return'], true) || $this->isEscalating() ? 'required' : 'nullable', 'string', 'max:2000'],
            'delegate_user_id' => [$action === 'delegate' ? 'required' : 'nullable', 'integer', 'exists:users,id'],
            // Keputusan eskalasi COO/CEO: wajib hanya saat IT menyetujui IT
            // Request (Hardware & Software) bertanda kebutuhan khusus.
            'escalation' => [$this->needsItEscalationDecision() ? 'required' : 'nullable', 'string', Rule::in(['none', 'coo_ceo'])],
            'escalate_role' => ['required_if:escalation,coo_ceo', 'nullable', 'string', Rule::in(['coo', 'ceo'])],
            'escalate_user_id' => ['required_if:escalation,coo_ceo', 'nullable', 'integer', 'exists:users,id'],
        ];
    }

    private function isEscalating(): bool
    {
        return (string) $this->route('action') === 'approve'
            && $this->input('escalation') === 'coo_ceo';
    }

    /**
     * True hanya untuk aksi approve pada tahap IT dari IT Request
     * bertanda special_specification. Scope sengaja dibatasi agar
     * ERP dan modul lain tidak terpengaruh.
     */
    private function needsItEscalationDecision(): bool
    {
        if ((string) $this->route('action') !== 'approve') {
            return false;
        }
        $approval = $this->route('approval');

        return $approval !== null
            && ($approval->step_code ?? null) === 'it'
            && ($approval->approvable ?? null) instanceof ItRequest
            && (bool) ($approval->approvable->special_specification ?? false);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $approval = $this->route('approval');
            if ($approval === null) {
                return;
            }

            $conflicts = array_merge(
                ApprovalActorConflicts::ids($approval->approvable, $approval),
                [(int) $this->user()->id],
                $approval->actions()->pluck('actor_id')->map(fn ($id) => (int) $id)->all(),
            );

            if ((string) $this->route('action') === 'delegate' && $this->filled('delegate_user_id')) {
                $target = User::query()->with('employee')->find($this->integer('delegate_user_id'));
                if (! $target || $target->active === false || ! $target->hasRole($approval->approver_role) || ! $target->can('approval.act') || ($target->employee && ! $target->employee->active) || in_array((int) $target->id, $conflicts, true)) {
                    $validator->errors()->add('delegate_user_id', 'Delegasi harus pengguna aktif dengan role dan permission approval yang sesuai.');
                }
            }

            if ((string) $this->route('action') !== 'approve' || $this->input('escalation') !== 'coo_ceo') {
                return;
            }

            // Target harus memegang role eksekutif yang dipilih (COO atau CEO),
            // bukan sekadar role payung lama.
            $role = (string) $this->input('escalate_role');
            $target = User::query()->with('employee')->find($this->integer('escalate_user_id'));
            if (! $target || $target->active === false || ! $target->hasRole($role) || ! $target->can('approval.act') || ($target->employee && ! $target->employee->active) || in_array((int) $target->id, $conflicts, true) || \App\Models\ApprovalRequest::query()->where('approvable_type', $approval->approvable_type)->where('approvable_id', $approval->approvable_id)->where('chain_generation', $approval->chain_generation)->where('status', \App\Enums\ApprovalStepStatus::Pending)->where('approver_user_id', $target->id)->exists()) {
                $validator->errors()->add('escalate_user_id', 'Target eskalasi harus pengguna COO/CEO yang aktif dan eligible untuk role yang dipilih.');
            }
        });
    }
}
