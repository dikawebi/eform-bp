<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email workflow eForm BP — satu template untuk seluruh module.
 * Isi berasal dari payload notifikasi database yang sama sehingga
 * inbox dan email tidak bisa berbeda isi.
 */
class WorkflowMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{event: string, title: string, message: string, meta: array<string, string|null>, action_url: string, company: array{name: string, address: string}}  $payload
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
}
