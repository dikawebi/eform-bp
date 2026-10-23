<?php

namespace App\Http\Controllers;

use App\Models\ItSpecTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ItSpecTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->guard($request);

        return Inertia::render('Master/ItSpecTemplates/Index', [
            'templates' => ItSpecTemplate::query()->ordered()->get(),
            'device_options' => ItRequestController::itemOptions('device'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $this->validated($request);

        $template = ItSpecTemplate::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'device_type' => $data['device_type'] ?: null,
            'specs_json' => $data['specs'],
            'is_active' => (bool) $data['is_active'],
            'sort_order' => $data['sort_order'] ?? 0,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        activity()->performedOn($template)->causedBy($request->user())
            ->withProperties(['code' => $template->code])->log('it.spec_template.created');

        return back()->with('success', 'Template spesifikasi IT berhasil dibuat.');
    }

    public function update(Request $request, ItSpecTemplate $it_spec_template): RedirectResponse
    {
        $this->guard($request);
        $data = $this->validated($request, $it_spec_template);

        $it_spec_template->forceFill([
            'code' => $data['code'],
            'name' => $data['name'],
            'device_type' => $data['device_type'] ?: null,
            'specs_json' => $data['specs'],
            'is_active' => (bool) $data['is_active'],
            'sort_order' => $data['sort_order'] ?? 0,
            'updated_by' => $request->user()->id,
        ])->save();

        activity()->performedOn($it_spec_template)->causedBy($request->user())
            ->withProperties(['code' => $it_spec_template->code, 'is_active' => $it_spec_template->is_active])
            ->log('it.spec_template.updated');

        return back()->with('success', 'Template spesifikasi IT berhasil diperbarui.');
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can('it.master.manage'), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ItSpecTemplate $existing = null): array
    {
        $deviceCodes = array_column(ItRequestController::itemOptions('device'), 'value');
        if ($existing?->device_type) {
            $deviceCodes[] = $existing->device_type;
        }

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('it_spec_templates', 'code')->ignore($existing?->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'device_type' => ['nullable', 'string', Rule::in(array_values(array_unique($deviceCodes)))],
            'specs' => ['required', 'array', 'min:1', 'max:30'],
            'specs.*.label' => ['required', 'string', 'max:100'],
            'specs.*.value' => ['required', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $labels = [];
        foreach ($data['specs'] as $index => $row) {
            $label = mb_strtolower(trim((string) $row['label']));
            if (in_array($label, $labels, true)) {
                throw ValidationException::withMessages([
                    "specs.{$index}.label" => 'Label spesifikasi tidak boleh duplikat.',
                ]);
            }
            $labels[] = $label;
        }

        $data['specs'] = collect($data['specs'])
            ->map(fn (array $row): array => [
                'label' => trim((string) $row['label']),
                'value' => trim((string) $row['value']),
            ])
            ->values()
            ->all();

        return $data;
    }
}
