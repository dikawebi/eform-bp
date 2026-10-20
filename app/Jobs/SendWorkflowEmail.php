<?php

namespace App\Jobs;

use App\Mail\WorkflowMail;
use App\Models\User;
use App\Services\InAppNotifier;
use App\Services\MailSettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Mengirim email untuk satu notifikasi workflow. Selalu dijalankan di
 * antrean database (afterCommit) supaya kegagalan SMTP tidak memengaruhi
 * transisi status; filter opt-out/blacklist dievaluasi di sini agar retry
 * tetap memakai preferensi terbaru.
 */
class SendWorkflowEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    /**
     * @param  array{event: string, title: string, message: string, url: string, meta: array}  $data
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $data,
    ) {}

    public function handle(): void
    {
        if (! MailSettingsService::emailEnabled()) {
            return;
        }

        $event = (string) ($this->data['event'] ?? '');
        if (in_array($event, (array) config('eform.mail.skip_events', []), true)) {
            return;
        }

        $user = User::query()->find($this->userId);
        if ($user === null || ! $user->email_notifications || trim((string) $user->email) === '') {
            return;
        }

        if (str_contains((string) $user->email, '.invalid')) {
            return;
        }

        $meta = array_intersect_key(
            (array) ($this->data['meta'] ?? []),
            array_flip(['module', 'document_number', 'actor', 'step']),
        );

        // SMTP dinamis diambil dari tabel mail_settings (Pengaturan > Email).
        MailSettingsService::apply();
        $company = (array) config('eform.company', []);
        $address = collect([$company['city'] ?? null, $company['regency'] ?? null, $company['province'] ?? null])
            ->filter()
            ->implode(' · ');

        Mail::to($user->email)->queue(new WorkflowMail([
            'event' => $event,
            'title' => (string) $this->data['title'],
            'message' => (string) $this->data['message'],
            'meta' => $meta,
            'action_url' => $this->actionUrl(),
            'company' => ['name' => $company['name'] ?? 'PT Borneo Prima', 'address' => $address],
        ]));
    }

    protected function actionUrl(): string
    {
        $path = InAppNotifier::resolveUrl($this->data);
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim((string) config('app.url'), '/').$path;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Email workflow gagal terkirim', [
            'event' => $this->data['event'] ?? null,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
