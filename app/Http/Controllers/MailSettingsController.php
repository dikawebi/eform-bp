<?php

namespace App\Http\Controllers;

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
    public function index(): Response
    {
        $settings = MailSettingsService::effective();
        $hasPassword = ($settings['password'] ?? null) !== null && trim((string) $settings['password']) !== '';
        $hasRow = MailSetting::query()->exists();

        unset($settings['password']);

        return Inertia::render('Settings/Mail/Index', [
            'settings' => $settings,
            'hasPassword' => $hasPassword,
            'hasRow' => $hasRow,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $data): void {
            $row = MailSetting::query()->orderBy('id')->lockForUpdate()->firstOrNew([]);
            $before = $this->auditView($row->exists ? $row->fresh() : $row);

            // Password hanya diubah ketika diisi; kosong = tetap.
            if (isset($data['password']) === false || trim((string) $data['password']) === '') {
                unset($data['password']);
            }

            $row->fill($data)->save();
            $row->refresh();

            activity()->performedOn($row)->causedBy($request->user())->withProperties([
                'before' => $before,
                'after' => $this->auditView($row->fresh()),
            ])->log('mail.settings.updated');
        });

        return back()->with('success', 'Pengaturan email tersimpan. Email percobaan dapat dikirim untuk memastikan SMTP bekerja.');
    }

    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate(['test_address' => ['required', 'email', 'max:190']]);

        try {
            MailSettingsService::sendTest($data['test_address']);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['test_address' => 'Pengiriman gagal: '.mb_substr($e->getMessage(), 0, 300)]);
        }

        activity()->causedBy($request->user())->log('mail.settings.test_sent');

        return back()->with('success', 'Email percobaan terkirim ke '.$data['test_address'].'. Cek folder inbox/spam.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'mailer' => ['required', Rule::in(['smtp', 'log'])],
            'host' => ['nullable', 'string', 'max:190'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email', 'max:190'],
            'from_name' => ['nullable', 'string', 'max:100'],
            'notifications_enabled' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditView(MailSetting $row): array
    {
        return [
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
