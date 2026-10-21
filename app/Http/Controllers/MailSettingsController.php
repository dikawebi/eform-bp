<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Models\MailSetting;
use App\Services\MailSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MailSettingsController extends Controller
{
    public function index(?string $module = null): Response
    {
        $module = in_array($module, array_keys(MailSettingsService::modules()), true) ? $module : null;
        $settings = MailSettingsService::effective($module);
        $hasPassword = ($settings['password'] ?? null) !== null && trim((string) $settings['password']) !== '';
        unset($settings['password']);

        $rows = [];
        foreach (array_keys(MailSettingsService::modules()) as $code) {
            $row = MailSetting::query()->where('module', $code)->first();
            $rows[$code ?? 'default'] = [
                'module' => $code,
                'label' => MailSettingsService::modules()[$code],
                'mailer' => $row?->mailer ?? '-',
                'host' => $row?->host ?? '-',
                'port' => $row?->port ?? '-',
                'from_address' => $row?->from_address ?? '-',
                'notifications_enabled' => $row?->notifications_enabled ?? true,
                'source' => $row ? ($code === null ? 'default' : 'modul') : 'env',
            ];
        }

        $templates = [];
        foreach (MailSettingsService::emailEvents($module) as $event => $label) {
            $row = EmailTemplate::query()->where('event', $event)
                ->where(function ($q) use ($module): void {
                    $q->where('module', $module)->orWhereNull('module');
                })
                ->orderByDesc('module')
                ->first();

            $templates[] = [
                'event' => $event,
                'label' => $label,
                'subject' => $row?->subject ?? '',
                'message' => $row?->message ?? '',
                'subject_en' => $row?->subject_en ?? '',
                'message_en' => $row?->message_en ?? '',
                'source' => $row ? ($row->module === null ? 'default' : 'modul') : 'notifikasi asli',
            ];
        }

        return Inertia::render('Settings/Mail/Index', [
            'module' => $module,
            'settings' => $settings,
            'templates' => $templates,
            'placeholders' => MailSettingsService::placeholders(),
            'modules' => MailSettingsService::modules(),
            'rows' => array_values($rows),
            'hasPassword' => $hasPassword,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $module = $request->input('module');
        if (! in_array($module, array_keys(MailSettingsService::modules()), true)) {
            $module = null;
        }

        $data = $request->validate([
            'mailer' => ['required', Rule::in(['smtp', 'log'])],
            'host' => ['nullable', 'string', 'max:190'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email', 'max:190'],
            'from_name' => ['nullable', 'string', 'max:100'],
            'notifications_enabled' => ['nullable', 'boolean'],
        ]);

        if (isset($data['password']) && trim((string) $data['password']) === '') {
            unset($data['password']);
        }

        DB::transaction(function () use ($request, $module, $data): void {
            $row = MailSetting::query()->updateOrCreate(
                ['module' => $module],
                $data
            );

            activity()->performedOn($row)->causedBy($request->user())->withProperties([
                'before' => null,
                'after' => $this->auditView($row->fresh()),
                'module' => $module,
            ])->log('mail.settings.updated');
        });

        return back()->with('success', 'Pengaturan email untuk '.MailSettingsService::modules()[$module].' tersimpan.');
    }

    public function test(Request $request): RedirectResponse
    {
        $module = $request->input('module');
        if (! in_array($module, array_keys(MailSettingsService::modules()), true)) {
            $module = null;
        }

        $data = $request->validate(['test_address' => ['required', 'email', 'max:190']]);

        try {
            MailSettingsService::sendTest($data['test_address'], $module);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['test_address' => 'Pengiriman gagal: '.mb_substr($e->getMessage(), 0, 300)]);
        }

        activity()->causedBy($request->user())->log('mail.settings.test_sent');

        return back()->with('success', 'Email percobaan terkirim ke '.$data['test_address'].' ('.MailSettingsService::modules()[$module].'). Cek folder inbox/spam.');
    }

    /**
     * @return array<string, mixed>
     */
    private function auditView(MailSetting $row): array
    {
        return [
            'module' => $row->module,
            'mailer' => $row->mailer,
            'host' => $row->host,
            'port' => $row->port,
            'encryption' => $row->encryption,
            'username' => $row->username,
            'password_set' => trim((string) $row->password) !== '',
            'from_address' => $row->from_address,
            'from_name' => $row->from_name,
            'notifications_enabled' => $row->notifications_enabled,
        ];
    }
}
