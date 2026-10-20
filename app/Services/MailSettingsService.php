<?php

namespace App\Services;

use App\Mail\WorkflowMail;
use App\Models\MailSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Pengaturan SMTP dinamis dari database (halaman Pengaturan > Email).
 * Nilai database mengganti konfigurasi .env saat runtime; job email
 * memanggil apply() sebelum mengirim sehingga antrean longevity tidak
 * memakai config yang basi.
 */
class MailSettingsService
{
    /**
     * Nilai efektif — baris database menggantikan nama environment .env.
     *
     * @return array{mailer: string, host: string|null, port: int|null, encryption: string|null, username: string|null, password: string|null, from_address: string|null, from_name: string|null, notifications_enabled: bool}
     */
    public static function effective(): array
    {
        $row = MailSetting::query()->orderBy('id')->first();
        $defaults = Config::get('mail');

        return [
            'mailer' => $row->mailer ?? (in_array(($defaults['default'] ?? ''), ['array', 'log'], true) ? $defaults['default'] : 'smtp'),
            'host' => $row->host ?? $defaults['mailers']['smtp']['host'] ?? null,
            'port' => $row->port ?? $defaults['mailers']['smtp']['port'] ?? null,
            'encryption' => $row->encryption ?? $defaults['mailers']['smtp']['encryption'] ?? null,
            'username' => $row->username ?? $defaults['mailers']['smtp']['username'] ?? null,
            'password' => $row?->password ?? $defaults['mailers']['smtp']['password'] ?? null,
            'from_address' => $row->from_address ?? $defaults['from']['address'] ?? null,
            'from_name' => $row->from_name ?? $defaults['from']['name'] ?? null,
            'notifications_enabled' => $row->notifications_enabled ?? true,
        ];
    }

    public static function apply(): void
    {
        $settings = self::effective();

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

        // Manager di-memo per nama mailer; buang instance agar configuracao baru
        // dipakai tanpa perlu restart queue worker.
        app()->forgetInstance('mail.manager');
    }

    /**
     * Kill-switch gabungan: config global (env) DAN switch di halaman settings.
     */
    public static function emailEnabled(): bool
    {
        return (bool) Config::get('eform.mail.enabled', true) && self::effective()['notifications_enabled'];
    }

    /**
     * Kirim email percobaan secara sinkron (dipakai tombol test di settings).
     */
    public static function sendTest(string $address): void
    {
        if (in_array(str_starts_with($address, 'mailto') ? $address : strtolower((string) $address), [], true)) {
            throw new RuntimeException('Alamat tidak valid.');
        }

        self::apply();
        $settings = self::effective();
        $company = (array) Config::get('eform.company', []);
        $address2 = collect([$company['city'] ?? null, $company['regency'] ?? null, $company['province'] ?? null])->filter()->implode(' · ');

        Mail::to($address)->send(new WorkflowMail([
            'event' => 'mail.test',
            'title' => 'Email percobaan eForm BP',
            'message' => 'Ini email percobaan dari halaman Pengaturan > Email. Jika email ini masuk, konfigurasi SMTP sudah benar.',
            'meta' => ['module' => 'Pengaturan Email'],
            'action_url' => rtrim((string) Config::get('app.url'), '/').'/dashboard',
            'company' => ['name' => $company['name'] ?? 'PT Borneo Prima', 'address' => $address2],
        ]));
    }
}
