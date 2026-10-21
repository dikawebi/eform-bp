<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Services\MailSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function index(?string $module = null): Response
    {
        $module = in_array($module, array_keys(MailSettingsService::modules()), true) ? $module : null;

        $templates = [];
        foreach (MailSettingsService::emailEvents() as $event => $label) {
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

        return Inertia::render('Settings/Mail/Templates/Index', [
            'module' => $module,
            'modules' => MailSettingsService::modules(),
            'templates' => $templates,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $module = $request->input('module');
        if (! in_array($module, array_keys(MailSettingsService::modules()), true)) {
            $module = null;
        }

        $events = MailSettingsService::emailEvents();
        $rules = [];
        foreach (array_keys($events) as $event) {
            $rules["subject.{$event}"] = ['nullable', 'string', 'max:255'];
            $rules["message.{$event}"] = ['nullable', 'string'];
            $rules["subject_en.{$event}"] = ['nullable', 'string', 'max:255'];
            $rules["message_en.{$event}"] = ['nullable', 'string'];
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($module, $data, $events): void {
            foreach (array_keys($events) as $event) {
                $subject = $data['subject'][$event] ?? null;
                $message = $data['message'][$event] ?? null;
                $subjectEn = $data['subject_en'][$event] ?? null;
                $messageEn = $data['message_en'][$event] ?? null;

                // Kosongkan semua = hapus baris template (kembali ke notifikasi asli).
                if ($subject === null && $message === null && $subjectEn === null && $messageEn === null) {
                    EmailTemplate::query()->where('module', $module)->where('event', $event)->delete();

                    continue;
                }

                EmailTemplate::query()->updateOrCreate(
                    ['module' => $module, 'event' => $event],
                    [
                        'subject' => $subject,
                        'message' => $message,
                        'subject_en' => $subjectEn,
                        'message_en' => $messageEn,
                    ]
                );
            }

            activity()->causedBy($request->user())->withProperties([
                'module' => $module,
            ])->log('mail.templates.updated');
        });

        return back()->with('success', 'Template email untuk '.MailSettingsService::modules()[$module].' tersimpan.');
    }
}
