<?php

namespace App\Services;

use App\Mail\WorkflowMail;
use App\Models\EmailTemplate;
use App\Models\MailSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Pengaturan SMTP dinamis per modul (halaman Pengaturan > Email).
 * Setiap modul (cuti, dinas, settlement, medical, IT, ERP) dapat memiliki
 * konfigurasi SMTP sendiri; modul tanpa baris sendiri mengikuti baris
 * default (module = NULL), yang kemudian mengikuti nilai .env.
 * Nilai database menggantikan konfigurasi .env saat runtime.
 */
class MailSettingsService
{
    /**
     * Daftar kode modul yang paling secara khusus dapat diatur.
     *
     * @return array<string, string>
     */
    public static function modules(): array
    {
        return [
            null => 'Default (semua modul)',
            'leave' => 'Cuti / Izin',
            'travel' => 'Perjalanan Dinas',
            'settlement' => 'Settlement',
            'medical' => 'Medical Claim',
            'it' => 'IT Request',
            'erp' => 'ERP Request',
        ];
    }

    /**
     * Daftar event notifikasi yang bisa diubah isi email-nya via halaman Pengaturan.
     * Saat modul dipilih, hanya event terkait modul itu (beserta approval generic)
     * yang ditampilkan. Semua modul (null) menampilkan seluruh event.
     *
     * @return array<string, string>
     */
    public static function emailEvents(?string $module = null): array
    {
        $all = [
            'approval.assigned' => 'Approval ditugaskan',
            'approval.returned' => 'Approval dikembalikan',
            'approval.rejected' => 'Approval ditolak',
            'approval.approved' => 'Approval disetujui',
            'approval.delegated' => 'Approval didelegasikan',
            'approval.cancelled' => 'Approval dibatalkan',
            'approval.withdrawn' => 'Pengajuan ditarik',
            'leave.submitted' => 'Cuti/izin diserahkan',
            'travel.submitted' => 'Perjalanan dinas diserahkan',
            'settlement.submitted' => 'Settlement diserahkan',
            'settlement.completed' => 'Settlement selesai',
            'medical.submitted' => 'Medical claim diserahkan',
            'it.submitted' => 'IT request diserahkan',
            'erp.submitted' => 'ERP request diserahkan',
            'it.completed' => 'IT request selesai',
            'it.pr_ready' => 'IT request siap dibuatkan PR',
            'erp.completed' => 'ERP request selesai',
        ];

        $moduleEvents = [
            'leave' => ['leave.submitted'],
            'travel' => ['travel.submitted'],
            'settlement' => ['settlement.submitted', 'settlement.completed'],
            'medical' => ['medical.submitted'],
            'it' => ['it.submitted', 'it.completed', 'it.pr_ready'],
            'erp' => ['erp.submitted', 'erp.completed'],
        ];

        if ($module === null) {
            return $all;
        }

        $events = array_merge($moduleEvents[$module] ?? [], array_filter(array_keys($all), fn ($e) => str_starts_with($e, 'approval.')));

        return array_intersect_key($all, array_flip($events));
    }

    /**
     * Placeholder yang dapat dipakai pada subjek/pesan template email.
     * Alias Bahasa Inggris disediakan agar template EN tetap mudah ditulis.
     *
     * @return array<string, string>
     */
    public static function placeholders(): array
    {
        return [
            '{nomor_dokumen}' => 'Nomor dokumen transaksi',
            '{modul}' => 'Nama modul transaksi',
            '{pelaku}' => 'Nama pengguna yang melakukan aksi',
            '{tahap}' => 'Tahap approval saat ini',
            '{judul}' => 'Judul notifikasi asli',
            '{pesan}' => 'Pesan notifikasi asli',
            '{tautan}' => 'Tautan langsung ke transaksi',
        ];
    }

    public static function stepLabel(?string $step): string
    {
        return match ($step) {
            'supervisor' => 'Supervisor',
            'hod' => 'HOD',
            'pm' => 'PM',
            'pm_gm' => 'PM/GM',
            'it' => 'IT',
            'erp_review' => 'Reviewer ERP',
            'coo_ceo' => 'COO/CEO',
            'coo' => 'COO',
            'ceo' => 'CEO',
            'hrga' => 'HRGA',
            'finance' => 'Finance',
            'document_validation' => 'Validasi Dokumen',
            default => (string) ($step ?? ''),
        };
    }

    /**
     * Ganti placeholder pada teks template dengan data notifikasi aktual.
     *
     * @param  array{title?: string|null, message?: string|null, url?: string|null, meta?: array<string, mixed>}  $data
     */
    public static function renderPlaceholders(?string $text, array $data): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $meta = (array) ($data['meta'] ?? []);
        $url = trim((string) ($data['url'] ?? ''));
        if ($url === '') {
            $link = '-';
        } elseif (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $link = $url;
        } else {
            $link = rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
        }

        $step = self::stepLabel(isset($meta['step']) ? (string) $meta['step'] : null);
        $documentNumber = (string) ($meta['document_number'] ?? '') ?: '-';
        $moduleName = (string) ($meta['module'] ?? '') ?: '-';
        $actor = (string) ($meta['actor'] ?? '') ?: '-';
        $title = (string) ($data['title'] ?? '') ?: '-';
        $message = (string) ($data['message'] ?? '') ?: '-';

        return strtr($text, [
            '{nomor_dokumen}' => $documentNumber,
            '{document_number}' => $documentNumber,
            '{modul}' => $moduleName,
            '{module}' => $moduleName,
            '{pelaku}' => $actor,
            '{actor}' => $actor,
            '{tahap}' => $step !== '' ? $step : '-',
            '{step}' => $step !== '' ? $step : '-',
            '{judul}' => $title,
            '{title}' => $title,
            '{pesan}' => $message,
            '{message}' => $message,
            '{tautan}' => $link,
            '{url}' => $link,
        ]);
    }

    /**
     * @return array{
     *   module: string|null,
     *   mailer: string,
     *   host: string|null,
     *   port: int|null,
     *   encryption: string|null,
     *   username: string|null,
     *   password: string|null,
     *   from_address: string|null,
     *   from_name: string|null,
     *   notifications_enabled: bool,
     *   source: string
     * }
     */
    public static function effective(?string $module = null): array
    {
        $defaults = Config::get('mail');
        $defaultMailer = (string) ($defaults['default'] ?? 'smtp');
        $fallbackMailer = in_array($defaultMailer, ['array', 'log'], true) ? $defaultMailer : 'smtp';

        $row = MailSetting::query()->where('module', $module)->first();
        $source = 'env';

        if ($row === null && $module !== null) {
            $row = MailSetting::query()->whereNull('module')->first();
            $source = $row ? 'default' : 'env';
        } elseif ($row !== null) {
            $source = $module === null ? 'default' : 'modul';
        }

        $get = fn (string $field, mixed $default): mixed => $row?->{$field} ?? $default;

        return [
            'module' => $module,
            'mailer' => $row?->mailer ?? $fallbackMailer,
            'host' => $get('host', $defaults['mailers']['smtp']['host'] ?? null),
            'port' => $get('port', $defaults['mailers']['smtp']['port'] ?? null),
            'encryption' => $get('encryption', $defaults['mailers']['smtp']['encryption'] ?? null),
            'username' => $get('username', $defaults['mailers']['smtp']['username'] ?? null),
            'password' => $row?->password ?? $defaults['mailers']['smtp']['password'] ?? null,
            'from_address' => $get('from_address', $defaults['from']['address'] ?? null),
            'from_name' => $get('from_name', $defaults['from']['name'] ?? null),
            'notifications_enabled' => $row?->notifications_enabled ?? true,
            'source' => $source,
        ];
    }

    public static function apply(?string $module = null): void
    {
        $settings = self::effective($module);

        Config::set('mail.default', $settings['mailer']);
        Config::set('mail.from.address', $settings['from_address'] ?? Config::get('mail.from.address'));
        Config::set('mail.from.name', $settings['from_name'] ?? Config::get('mail.from.name'));
        Config::set('mail.mailers.smtp.host', $settings['host'] ?? Config::get('mail.mailers.smtp.host'));
        Config::set('mail.mailers.smtp.port', $settings['port'] ?? Config::get('mail.mailers.smtp.port'));
        Config::set('mail.mailers.smtp.encryption', $settings['encryption'] ?: Config::get('mail.mailers.smtp.encryption'));
        Config::set('mail.mailers.smtp.username', $settings['username'] ?? Config::get('mail.mailers.smtp.username'));

        if ($settings['password'] !== null && $settings['password'] !== '') {
            Config::set('mail.mailers.smtp.password', $settings['password']);
        }

        app()->forgetInstance('mail.manager');
    }

    /**
     * Kill-switch gabungan: config global (env) DAN switch per modul di settings.
     */
    public static function emailEnabled(?string $module = null): bool
    {
        return (bool) Config::get('eform.mail.enabled', true) && self::effective($module)['notifications_enabled'];
    }

    /**
     * Kirim email percobaan secara sinkron dengan konfigurasi modul tertentu.
     */
    public static function sendTest(string $address, ?string $module = null): void
    {
        if (trim((string) $address) === '' || ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Alamat email tidak valid.');
        }

        self::apply($module);
        $settings = self::effective($module);
        $company = (array) Config::get('eform.company', []);
        $address2 = collect([$company['city'] ?? null, $company['regency'] ?? null, $company['province'] ?? null])->filter()->implode(' · ');
        $moduleLabel = self::modules()[$module] ?? 'Default';

        Mail::to($address)->send(new WorkflowMail([
            'event' => 'mail.test',
            'title' => 'Email percobaan eForm BP — '.ucfirst((string) $moduleLabel),
            'message' => 'Ini email percobaan dari halaman Pengaturan > Email untuk modul '.$moduleLabel.'. Jika email ini masuk, konfigurasi SMTP modul ini sudah benar.',
            'title_en' => 'eForm BP Test Email — '.ucfirst((string) $moduleLabel),
            'message_en' => 'This is a test email from the Settings > Email page for the '.$moduleLabel.' module. If this email arrives, the SMTP configuration for this module is correct.',
            'meta' => ['module' => 'Pengaturan Email'],
            'action_url' => route('dashboard'),
            'company' => ['name' => $company['name'] ?? 'PT Borneo Prima', 'address' => $address2],
        ]));
    }

    /**
     * Resolusi template email kustom untuk modul + event.
     * Prioritas: baris dengan module yang cocok > baris module NULL (default).
     *
     * @return array{subject: string|null, message: string|null, subject_en: string|null, message_en: string|null}
     */
    public static function resolveTemplate(?string $module, string $event): array
    {
        $row = EmailTemplate::query()->where('event', $event)
            ->where(function ($query) use ($module): void {
                $query->where('module', $module)->orWhereNull('module');
            })
            ->orderByDesc('module') // module spesifik menang terhadap NULL
            ->first();

        if ($row === null) {
            return ['subject' => null, 'message' => null, 'subject_en' => null, 'message_en' => null];
        }

        return [
            'subject' => $row->subject,
            'message' => $row->message,
            'subject_en' => $row->subject_en,
            'message_en' => $row->message_en,
        ];
    }
}
