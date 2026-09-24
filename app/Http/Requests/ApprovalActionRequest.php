<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\Approval\ApprovalActorConflicts;
use Illuminate\Foundation\Http\FormRequest;

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
            'comments' => [in_array($action, ['reject', 'return'], true) ? 'required' : 'nullable', 'string', 'max:2000'],
            'delegate_user_id' => [$action === 'delegate' ? 'required' : 'nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ((string) $this->route('action') !== 'delegate' || ! $this->filled('delegate_user_id')) {
                return;
            }
            $approval = $this->route('approval');
            $target = User::query()->with('employee')->find($this->integer('delegate_user_id'));
            $parent = $approval?->approvable;
            $conflicts = array_merge(ApprovalActorConflicts::ids($parent, $approval), [(int) $this->user()->id], $approval->actions()->pluck('actor_id')->map(fn ($id) => (int) $id)->all());
            if (! $target || $target->active === false || ! $target->hasRole($approval->approver_role) || ! $target->can('approval.act') || ($target->employee && ! $target->employee->active) || in_array((int) $target->id, $conflicts, true)) {
                $validator->errors()->add('delegate_user_id', 'Delegasi harus pengguna aktif dengan role dan permission approval yang sesuai.');
            }
        });
    }
}
