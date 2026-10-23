<?php

namespace App\Services\It;

use App\Models\ItRequest;
use App\Models\ItRequestSpecification;
use App\Models\ItSpecTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveItRequestSpecification
{
    /**
     * @param  array{template_id?: int|string|null, specs: array<int, array{label: string, value: string}>, notes?: string|null}  $data
     */
    public static function save(ItRequest $request, User $actor, array $data): ItRequestSpecification
    {
        return DB::transaction(function () use ($request, $actor, $data): ItRequestSpecification {
            $request = ItRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if (! ItSpecificationAccess::canEdit($request, $actor)) {
                throw ValidationException::withMessages([
                    'specification' => 'Spesifikasi hanya dapat diisi oleh reviewer IT yang sedang ditugaskan.',
                ]);
            }

            $template = null;
            $templateId = $data['template_id'] ?? null;
            if ($templateId !== null && $templateId !== '') {
                $template = ItSpecTemplate::query()->whereKey((int) $templateId)->where('is_active', true)->first();
                if ($template === null) {
                    throw ValidationException::withMessages([
                        'template_id' => 'Template spesifikasi tidak aktif atau tidak ditemukan.',
                    ]);
                }
                if ($request->device_type !== null
                    && $template->device_type !== null
                    && $template->device_type !== $request->device_type) {
                    throw ValidationException::withMessages([
                        'template_id' => 'Template tidak sesuai dengan jenis perangkat pada request.',
                    ]);
                }
            }

            $rows = collect((array) $data['specs'])
                ->map(fn (array $row): array => [
                    'label' => trim((string) ($row['label'] ?? '')),
                    'value' => trim((string) ($row['value'] ?? '')),
                ])
                ->filter(fn (array $row): bool => $row['label'] !== '' && $row['value'] !== '')
                ->values()
                ->all();

            if ($rows === []) {
                throw ValidationException::withMessages([
                    'specs' => 'Minimal satu baris spesifikasi harus diisi.',
                ]);
            }

            $specification = ItRequestSpecification::query()->firstOrNew(['it_request_id' => $request->getKey()]);
            $wasLocked = $specification->locked_at !== null;

            $specification->forceFill([
                'template_id' => $template?->getKey(),
                'device_type' => $request->device_type,
                'specs_json' => $rows,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'specified_by' => $actor->getKey(),
                'specified_at' => $specification->specified_at ?? now(),
                'locked_by' => null,
                'locked_at' => null,
            ])->save();

            activity()->performedOn($request)->causedBy($actor)->withProperties([
                'specification_id' => $specification->getKey(),
                'template_id' => $specification->template_id,
                'device_type' => $specification->device_type,
                'rows' => count($rows),
                'was_locked' => $wasLocked,
            ])->log('it.specification_saved');

            return $specification;
        });
    }

    public static function lockForApproval(ItRequest $request, User $actor): ItRequestSpecification
    {
        $specification = $request->specification()->lockForUpdate()->first();

        if ($specification === null || empty($specification->specs_json)) {
            throw ValidationException::withMessages([
                'specification' => 'Spesifikasi teknis wajib diisi IT sebelum menyetujui.',
            ]);
        }

        if ($request->device_type !== null
            && $specification->device_type !== null
            && $specification->device_type !== $request->device_type) {
            throw ValidationException::withMessages([
                'specification' => 'Jenis perangkat pada spesifikasi tidak sama dengan request. Perbarui spesifikasi terlebih dahulu.',
            ]);
        }

        $specification->forceFill([
            'locked_by' => $actor->getKey(),
            'locked_at' => now(),
        ])->save();

        activity()->performedOn($request)->causedBy($actor)->withProperties([
            'specification_id' => $specification->getKey(),
            'template_id' => $specification->template_id,
            'device_type' => $specification->device_type,
            'rows' => count((array) $specification->specs_json),
        ])->log('it.specification_locked');

        return $specification;
    }
}
