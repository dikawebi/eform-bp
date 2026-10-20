<?php

namespace App\Services;

use App\Jobs\SendWorkflowEmail;
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
     * @param  array{module?: string, document_number?: string, actor?: string, step?: string}  $meta
     */
    public function notifyUser(?int $userId, string $event, string $title, string $message, string $url, array $meta = []): void
    {
        if ($userId === null) {
            return;
        }

        $user = User::query()->whereKey($userId)->first();

        if ($user === null) {
            return;
        }

        $user->notify(new WorkflowNotification($event, $title, $message, $url, $meta));

        // Email mengikuti seluruh notifikasi database; pengiriman diantrikan
        // (afterCommit) lewat job supaya SMTP tidak memengaruhi transaksi.
        SendWorkflowEmail::dispatch($user->getKey(), [
            'event' => $event,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'meta' => $meta,
        ])->afterCommit();
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
     * modul, nomor dokumen, pelaku, dan kode tahap (bila relevan).
     *
     * @return array{module: string, document_number: string|null, actor: string|null, step: string|null}
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
            'document_number' => $number !== null ? (string) $number : null,
            'actor' => $actor?->name,
            'step' => $step,
        ];
    }
}
