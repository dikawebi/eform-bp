<?php

namespace App\Http\Requests;

use App\Models\ItRequest;
use App\Models\ItSpecTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class SaveItSpecificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('it_request');

        return $request instanceof ItRequest
            && $this->user()?->can('saveSpecification', $request) === true;
    }

    public function rules(): array
    {
        return [
            'template_id' => ['nullable', 'integer', 'exists:it_spec_templates,id'],
            'specs' => ['required', 'array', 'min:1', 'max:30'],
            'specs.*.label' => ['required', 'string', 'max:100'],
            'specs.*.value' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $request = $this->route('it_request');
            if (! $request instanceof ItRequest) {
                return;
            }

            $labels = [];
            foreach ((array) $this->input('specs', []) as $index => $row) {
                $label = mb_strtolower(trim((string) ($row['label'] ?? '')));
                if ($label !== '' && in_array($label, $labels, true)) {
                    $validator->errors()->add("specs.{$index}.label", 'Label spesifikasi tidak boleh duplikat.');
                }
                $labels[] = $label;
            }

            $templateId = (int) ($this->input('template_id') ?? 0);
            if ($templateId === 0) {
                return;
            }

            $template = ItSpecTemplate::query()->find($templateId);
            if ($template === null || ! $template->is_active) {
                $validator->errors()->add('template_id', 'Template spesifikasi tidak aktif atau tidak ditemukan.');

                return;
            }

            if ($request->device_type !== null
                && $template->device_type !== null
                && $template->device_type !== $request->device_type) {
                $validator->errors()->add('template_id', 'Template tidak sesuai dengan jenis perangkat pada request.');
            }
        });
    }

    protected function failedAuthorization(): void
    {
        throw ValidationException::withMessages([
            'specification' => 'Spesifikasi hanya dapat diisi oleh reviewer IT yang sedang ditugaskan.',
        ]);
    }
}
