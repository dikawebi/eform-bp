<?php

namespace App\Http\Requests;

use App\Models\ApprovalWorkflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApprovalWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workflow = $this->route('workflow');

        return $workflow instanceof ApprovalWorkflow
            && $this->user()?->can('update', $workflow) === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'steps' => ['required', 'array', 'min:1', 'max:50'],
            'steps.*.step_order' => ['required', 'integer', 'min:1', 'max:1000', 'distinct'],
            'steps.*.step_code' => ['required', Rule::in([
                'supervisor', 'hod', 'pm', 'hrga', 'document_validation', 'finance',
            ]), 'distinct'],
            'steps.*.approver_role' => ['required', Rule::exists('roles', 'name')->where(fn ($query) => $query->where('guard_name', 'web'))],
            'steps.*.approver_resolver' => ['required', Rule::in([
                'supervisor_id', 'hod_id', 'assigned_pm', 'role_users',
            ])],
            'steps.*.is_required' => ['required', 'boolean'],
            'steps.*.can_skip_if_no_supervisor' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('steps'))) {
            $this->merge(['steps' => array_values($this->input('steps'))]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $orders = collect($this->input('steps', []))->pluck('step_order')->map(fn ($order) => (int) $order)->sort()->values()->all();
            $expected = range(1, count($orders));

            if ($orders !== $expected) {
                $validator->errors()->add('steps', 'Urutan langkah harus berurutan mulai dari 1.');
            }

            foreach ($this->input('steps', []) as $index => $step) {
                if (($step['can_skip_if_no_supervisor'] ?? false) && ($step['approver_resolver'] ?? null) !== 'supervisor_id') {
                    $validator->errors()->add("steps.{$index}.can_skip_if_no_supervisor", 'Opsi lewati hanya berlaku untuk resolver supervisor.');
                }
            }
        });
    }
}
