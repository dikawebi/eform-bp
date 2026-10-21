<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Email workflow eForm BP — satu template untuk seluruh module.
 * Isi berasal dari payload notifikasi database yang sama sehingga
 * inbox dan email tidak bisa berbeda isi.
 */
class WorkflowMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{event: string, title: string, message: string, title_en?: string|null, message_en?: string|null, meta: array<string, string|null>, progress?: list<array{code: string|null, label: string, status: string, approver: string|null, acted_at: string|null}>, attachments?: list<array{disk: string, path: string, name: string, mime: string}>, action_url: string, company: array{name: string, address: string}}  $payload
     */
    public function __construct(public readonly array $payload) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->payload['title'],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.workflow', with: ['payload' => $this->payload]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return collect($this->payload['attachments'] ?? [])
            ->filter(function (array $attachment): bool {
                $disk = (string) ($attachment['disk'] ?? 'eform-private');
                $path = (string) ($attachment['path'] ?? '');

                return $path !== '' && Storage::disk($disk)->exists($path);
            })
            ->map(fn (array $attachment): Attachment => Attachment::fromStorageDisk(
                (string) ($attachment['disk'] ?? 'eform-private'),
                (string) $attachment['path'],
            )->as((string) ($attachment['name'] ?? basename((string) $attachment['path'])))
                ->withMime((string) ($attachment['mime'] ?? 'application/pdf')))
            ->values()
            ->all();
    }
}
