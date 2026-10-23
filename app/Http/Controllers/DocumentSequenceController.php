<?php

namespace App\Http\Controllers;

use App\Models\DocumentSequence;
use App\Services\DocumentNumberSequence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DocumentSequenceController extends Controller
{
    public function index(): Response
    {
        DocumentNumberSequence::ensureDefaults();

        $rows = DocumentSequence::query()
            ->whereIn('module', array_keys(DocumentSequence::MODULES))
            ->get()
            ->keyBy('module');

        $sequences = collect(DocumentSequence::MODULES)
            ->map(function (array $meta, string $module) use ($rows): array {
                /** @var DocumentSequence $row */
                $row = $rows->get($module);

                return [
                    'module' => $module,
                    'label' => $meta['label'],
                    'prefix' => $row->prefix,
                    'separator' => $row->separator,
                    'period_format' => (string) ($row->period_format ?? ''),
                    'period' => (string) ($row->period() ?? ''),
                    'padding' => (int) $row->padding,
                    'next_number' => (int) $row->next_number,
                    'preview' => $row->preview(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Settings/Sequences/Index', [
            'sequences' => $sequences,
            'period_formats' => [
                ['value' => 'Ym', 'label' => 'Bulanan — 202610'],
                ['value' => 'Y', 'label' => 'Tahunan — 2026'],
                ['value' => 'Ymd', 'label' => 'Harian — 20261023'],
                ['value' => '', 'label' => 'Tanpa reset — lanjut terus'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sequences' => ['required', 'array'],
            'sequences.*.prefix' => ['required', 'string', 'max:20'],
            'sequences.*.separator' => ['required', 'string', 'max:3'],
            'sequences.*.period_format' => ['nullable', 'string', 'max:10', 'regex:/^[Yymd]*$/'],
            'sequences.*.padding' => ['required', 'integer', 'between:1,8'],
            'sequences.*.next_number' => ['required', 'integer', 'between:1,999999999'],
        ]);

        $updates = array_intersect_key($data['sequences'], DocumentSequence::MODULES);
        if ($updates === []) {
            throw ValidationException::withMessages(['sequences' => 'Tidak ada sequence yang dipilih.']);
        }

        DocumentNumberSequence::ensureDefaults();

        DB::transaction(function () use ($updates, $request): void {
            foreach ($updates as $module => $values) {
                $sequence = DocumentSequence::query()
                    ->where('module', $module)
                    ->lockForUpdate()
                    ->firstOrFail();

                $before = [
                    'prefix' => $sequence->prefix,
                    'separator' => $sequence->separator,
                    'period_format' => $sequence->period_format,
                    'padding' => $sequence->padding,
                    'next_number' => $sequence->next_number,
                ];

                $sequence->fill([
                    'prefix' => trim((string) $values['prefix']),
                    'separator' => trim((string) $values['separator']),
                    'period_format' => filled($values['period_format'] ?? null)
                        ? trim((string) $values['period_format'])
                        : null,
                    'padding' => (int) $values['padding'],
                    'next_number' => (int) $values['next_number'],
                ]);

                $period = $sequence->period();
                $preview = $sequence->formatNumber((int) $sequence->next_number, $period);

                if (DocumentNumberSequence::isNumberUsed($module, $preview)) {
                    throw ValidationException::withMessages([
                        "sequences.{$module}.next_number" => "Nomor {$preview} sudah digunakan. Pilih nomor berikutnya yang lain.",
                    ]);
                }

                $sequence->last_period = $period;
                $sequence->save();

                activity()->performedOn($sequence)->causedBy($request->user())->withProperties([
                    'before' => $before,
                    'after' => [
                        'prefix' => $sequence->prefix,
                        'separator' => $sequence->separator,
                        'period_format' => $sequence->period_format,
                        'padding' => $sequence->padding,
                        'next_number' => $sequence->next_number,
                    ],
                ])->log('sequence.updated');
            }
        });

        return back()->with('success', 'Number sequence berhasil diperbarui.');
    }
}
