<?php

namespace App\Services;

use App\Enums\ApprovalStepStatus;
use App\Jobs\SendWorkflowEmail;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\ErpRequest;
use App\Models\ItRequest;
use App\Models\LeaveRequest;
use App\Models\MedicalClaim;
use App\Models\Settlement;
use App\Models\TravelRequest;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Database\Eloquent\Model;

final class InAppNotifier
{
    /**
     * @param  array{module?: string, module_code?: string|null, document_number?: string|null, actor?: string|null, step?: string|null, approvable_type?: string, approvable_id?: int|string}  $meta
     * @param  list<array{disk: string, path: string, name: string, mime: string}>  $attachments
     */
    public function notifyUser(?int $userId, string $event, string $title, string $message, string $url, array $meta = [], ?string $titleEn = null, ?string $messageEn = null, array $attachments = []): void
    {
        if ($userId === null) {
            return;
        }

        $user = User::query()->whereKey($userId)->first();

        if ($user === null) {
            return;
        }

        $user->notify(new WorkflowNotification($event, $title, $message, $url, $meta, $titleEn, $messageEn));

        // Email mengikuti seluruh notifikasi database; pengiriman diantrikan
        // (afterCommit) lewat job supaya SMTP tidak memengaruhi transaksi.
        SendWorkflowEmail::dispatch($user->getKey(), [
            'event' => $event,
            'title' => $title,
            'message' => $message,
            'title_en' => $titleEn,
            'message_en' => $messageEn,
            'url' => $url,
            'meta' => $meta,
            'attachments' => $attachments,
        ])->afterCommit();
    }

    public function notifyEmployeeLinked(User $user, Employee $employee, ?User $actor = null): void
    {
        $this->notifyUser(
            $user->getKey(),
            'user.employee_linked',
            'Akun berhasil terhubung ke data karyawan',
            "Akun eForm BP Anda ({$user->email}) telah dihubungkan ke data karyawan NIK {$employee->employee_number} ({$employee->name}). Anda sekarang dapat membuat pengajuan untuk data karyawan tersebut.",
            route('dashboard'),
            [
                'module' => 'Akun Pengguna',
                'module_code' => null,
                'document_number' => $employee->employee_number,
                'actor' => $actor?->name,
            ],
            'Account linked to employee data',
            "Your eForm BP account ({$user->email}) has been linked to employee NIK {$employee->employee_number} ({$employee->name}). You can now create requests for this employee.",
        );
    }

    public function notifyOwner(Model $subject, string $event, string $title, string $message, ?User $actor = null): void
    {
        $subject->loadMissing('employee:id,user_id');
        $employee = $subject->employee;

        if (! $employee?->user_id) {
            return;
        }

        $this->notifyUser($employee->user_id, $event, $title, $message, $this->url($subject), self::context($subject, $actor));
    }

    public function url(Model $subject): string
    {
        return match (true) {
            $subject instanceof LeaveRequest => route('leaves.show', $subject),
            $subject instanceof TravelRequest => route('travels.show', $subject),
            $subject instanceof Settlement => route('settlements.show', $subject),
            $subject instanceof MedicalClaim => route('medical-claims.show', $subject),
            $subject instanceof ItRequest => route('it-requests.show', $subject),
            $subject instanceof ErpRequest => route('erp-requests.show', $subject),
            default => route('dashboard'),
        };
    }

    public function label(Model $subject): string
    {
        return match (true) {
            $subject instanceof LeaveRequest => 'Pengajuan cuti/izin',
            $subject instanceof TravelRequest => 'Pengajuan perjalanan dinas',
            $subject instanceof Settlement => 'Settlement',
            $subject instanceof MedicalClaim => 'Medical Claim',
            $subject instanceof ItRequest => 'IT Request',
            $subject instanceof ErpRequest => 'ERP Request',
            default => 'Transaksi eForm BP',
        };
    }

    /**
     * Ubah URL tersimpan menjadi path internal yang aman dikunjungi.
     * Menerima path relatif (`/approvals/10`) maupun URL absolut
     * (`http://localhost/it-requests/5`) karena `route()` menyimpan
     * bentuk absolut; selain itu jatuh ke dashboard.
     */
    public static function resolveUrl(mixed $data): string
    {
        $url = trim((string) data_get($data, 'url', ''));
        if ($url === '') {
            return route('dashboard');
        }
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        if ($path !== '' && str_starts_with($path, '/')) {
            $query = (string) (parse_url($url, PHP_URL_QUERY) ?? '');

            return $query !== '' ? "{$path}?{$query}" : $path;
        }

        return route('dashboard');
    }

    /**
     * Metadata terstruktur untuk tampilan notifikasi yang lebih detail:
     * modul, nomor dokumen, pelaku, kode tahap, dan referensi dokumen
     * untuk menyusun progress approval pada email.
     *
     * @return array{module: string, module_code: string|null, document_number: string|null, actor: string|null, step: string|null, approvable_type: string, approvable_id: int|string}
     */
    public static function context(Model $subject, ?User $actor = null, ?string $step = null): array
    {
        $notifier = new self;

        $number = match (true) {
            $subject instanceof Settlement => $subject->settlement_number,
            $subject instanceof MedicalClaim => $subject->claim_number,
            $subject instanceof LeaveRequest, $subject instanceof TravelRequest,
            $subject instanceof ItRequest, $subject instanceof ErpRequest => $subject->request_number,
            default => null,
        };

        return [
            'module' => $notifier->label($subject),
            'module_code' => match (true) {
                $subject instanceof LeaveRequest => 'leave',
                $subject instanceof TravelRequest => 'travel',
                $subject instanceof Settlement => 'settlement',
                $subject instanceof MedicalClaim => 'medical',
                $subject instanceof ItRequest => 'it',
                $subject instanceof ErpRequest => 'erp',
                default => null,
            },
            'document_number' => $number !== null ? (string) $number : null,
            'actor' => $actor?->name,
            'step' => $step,
            'approvable_type' => $subject->getMorphClass(),
            'approvable_id' => $subject->getKey(),
        ];
    }

    /**
     * Susunan tahap approval terbaru untuk ditampilkan sebagai stepper.
     * Dipanggil saat job email berjalan (setelah commit) agar seluruh
     * rantai sudah tersimpan, termasuk status tiap tahap.
     *
     * @return list<array{code: string|null, label: string, status: string, approver: string|null, acted_at: string|null}>
     */
    public static function approvalProgress(string $type, int $id): array
    {
        if ($type === '' || $id <= 0) {
            return [];
        }

        $steps = ApprovalRequest::query()
            ->where('approvable_type', $type)
            ->where('approvable_id', $id)
            ->currentChain()
            ->with('approver:id,name')
            ->orderBy('step_order')
            ->orderBy('id')
            ->get();

        // Tahap setelah approver aktif disimpan sebagai Cancelled sebagai
        // penanda "belum giliran". Tampilkan sebagai menunggu, kecuali rantai
        // sudah berhenti (tidak ada tahap pending) sehingga benar-benar batal.
        $pendingOrder = $steps->first(fn (ApprovalRequest $step): bool => $step->status === ApprovalStepStatus::Pending)?->step_order;

        return $steps
            ->map(function (ApprovalRequest $approval) use ($pendingOrder): array {
                $status = $approval->status->value;
                if ($approval->status === ApprovalStepStatus::Cancelled
                    && $pendingOrder !== null
                    && $approval->step_order > $pendingOrder) {
                    $status = ApprovalStepStatus::Pending->value;
                }

                return [
                    'code' => $approval->step_code,
                    'label' => MailSettingsService::stepLabel($approval->step_code),
                    'status' => $status,
                    'approver' => $approval->approver?->name,
                    'acted_at' => $approval->acted_at?->translatedFormat('d M Y'),
                ];
            })
            ->all();
    }
}
