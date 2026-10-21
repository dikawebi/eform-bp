<?php

namespace App\Services\It;

use App\Enums\ApprovalStepStatus;
use App\Models\ItItemOption;
use App\Models\ItRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dokumen PDF serah terima IT Request ke Commercial sebagai dasar PR.
 * Dipakai oleh lampiran email saat seluruh approval selesai.
 */
final class ItRequestPdf
{
    public const DISK = 'eform-private';

    /**
     * @return array{disk: string, path: string, name: string, mime: string}
     */
    public static function store(ItRequest $request): array
    {
        $path = 'it-requests/pr-ready/'.$request->request_number.'.pdf';
        $stored = Storage::disk(self::DISK)->put($path, self::render($request));

        if (! $stored) {
            throw new \RuntimeException('Dokumen PDF IT request gagal disimpan.');
        }

        return [
            'disk' => self::DISK,
            'path' => $path,
            'name' => 'IT-Request-'.$request->request_number.'.pdf',
            'mime' => 'application/pdf',
        ];
    }

    public static function render(ItRequest $request): string
    {
        return Pdf::loadHTML(self::html($request))->setPaper('a4', 'portrait')->output();
    }

    public static function html(ItRequest $request): string
    {
        $request->loadMissing([
            'employee:id,employee_number,name,department,job_title',
            'approvalRequests.approver:id,name',
            'approvalRequests.actions.actor:id,name',
        ]);

        return view('pdf.it-request', [
            'doc' => self::data($request),
            'logo' => self::logoDataUri(),
        ])->render();
    }

    /**
     * @return array<string, mixed>
     */
    private static function data(ItRequest $request): array
    {
        $optionLabels = ItItemOption::query()->pluck('label', 'code')->all();
        $typeLabels = ['new_item' => 'Barang baru', 'replacement' => 'Penggantian'];
        $statusLabels = [
            'draft' => 'Draft', 'submitted' => 'Diajukan', 'in_review' => 'Dalam Review',
            'returned' => 'Dikembalikan', 'rejected' => 'Ditolak', 'approved' => 'Disetujui',
            'processing' => 'Diproses', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan',
        ];
        $replacementLabels = [
            'not_suitable' => 'Tidak sesuai kebutuhan', 'damaged' => 'Rusak', 'other' => 'Lainnya',
        ];
        $stepLabels = [
            'hod' => 'HOD', 'it' => 'IT', 'pm_gm' => 'PM/GM Site', 'coo_ceo' => 'COO/CEO',
        ];

        $softwareOptional = collect($request->software_optional_json ?? [])
            ->map(fn (array $item): string => trim((string) ($item['name'] ?? '')).(($item['license'] ?? null) === 'existing' ? ' (lisensi tersedia)' : ' (lisensi baru)'))
            ->filter()
            ->values()
            ->all();

        $accessories = collect($request->accessories_json ?? [])
            ->map(fn (string $code): string => $optionLabels[$code] ?? $code)
            ->values()
            ->all();

        $signatures = [[
            'title' => 'Diminta oleh',
            'name' => $request->employee?->name ?? $request->recipient_name ?? '-',
            'date' => $request->submitted_at?->translatedFormat('d M Y') ?? '.... / .... / ....',
            'requested' => true,
        ]];

        $latestGeneration = $request->approvalRequests->sortByDesc('id')->first()?->chain_generation;
        $approvedSteps = $request->approvalRequests
            ->where('chain_generation', $latestGeneration)
            ->where('status', ApprovalStepStatus::Approved)
            ->sortBy('step_order')
            ->unique('step_code');

        foreach ($approvedSteps as $approval) {
            $signatures[] = [
                'title' => $stepLabels[$approval->step_code] ?? ucfirst((string) $approval->step_code),
                'name' => $approval->actions->sortByDesc('id')->first()?->actor?->name ?? $approval->approver?->name ?? '-',
                'date' => $approval->acted_at?->translatedFormat('d M Y') ?? '-',
                'requested' => false,
            ];
        }

        $company = (array) config('eform.company', []);

        return [
            'number' => $request->request_number,
            'title' => 'IT & Telecommunication Acquisition Request',
            'company' => [
                'name' => $company['name'] ?? 'PT Borneo Prima',
                'address' => collect([$company['city'] ?? null, $company['regency'] ?? null, $company['province'] ?? null])->filter()->implode(' · '),
            ],
            'status' => $statusLabels[$request->status->value] ?? $request->status->value,
            'completed_at' => $request->completed_at?->translatedFormat('d M Y') ?? '-',
            'recipient' => [
                'name' => $request->employee?->name ?? $request->recipient_name ?? '-',
                'nik' => $request->employee?->employee_number ?? '-',
                'department' => $request->employee?->department ?? '-',
                'job_title' => $request->employee?->job_title ?? '-',
                'type' => $typeLabels[$request->request_type] ?? $request->request_type ?? '-',
                'site' => collect([$request->recipient_site, $request->recipient_cost_code])->filter()->implode(' · ') ?: '-',
            ],
            'special_specification' => (bool) $request->special_specification,
            'device' => $request->device_type ? ($optionLabels[$request->device_type] ?? $request->device_type) : '-',
            'accessories' => $accessories,
            'accessory_other_note' => $request->accessory_other_note,
            'software_standard' => $request->software_standard_json ?? [],
            'software_optional' => $softwareOptional,
            'description' => Str::limit(trim((string) $request->description), 220) ?: '-',
            'purpose' => Str::limit(trim((string) $request->purpose), 220) ?: '-',
            'replacement_reason' => $request->replacement_reason
                ? ($replacementLabels[$request->replacement_reason] ?? $request->replacement_reason)
                : null,
            'replacement_note' => $request->replacement_note,
            'signatures' => $signatures,
        ];
    }

    private static function logoDataUri(): string
    {
        $path = public_path('images/logo-bp.png');

        if (! is_file($path)) {
            return '';
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }
}
