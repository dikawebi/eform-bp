<?php

namespace Tests\Feature;

use App\Jobs\SendWorkflowEmail;
use App\Mail\WorkflowMail;
use App\Models\User;
use App\Services\InAppNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WorkflowEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_database_notification_dispatches_email_job_after_commit(): void
    {
        Queue::fake();

        $user = $this->userWithEmail();
        app(InAppNotifier::class)->notifyUser($user->id, 'it.submitted', 'Judul', 'Pesan', '/it-requests/1', ['module' => 'IT Request']);

        $this->assertDatabaseCount('notifications', 1);
        Queue::assertPushed(SendWorkflowEmail::class, fn (SendWorkflowEmail $job) => $job->userId === $user->id
            && $job->data['event'] === 'it.submitted');
    }

    public function test_job_sends_email_with_payload_to_user(): void
    {
        Mail::fake();

        $user = $this->userWithEmail();
        (new SendWorkflowEmail($user->id, [
            'event' => 'travel.submitted',
            'title' => 'Pengajuan perjalanan dinas diajukan',
            'message' => 'Pengajuan TRV-202610-0007 menunggu persetujuan Anda.',
            'url' => route('travels.show', 3),
            'meta' => ['module' => 'Pengajuan perjalanan dinas', 'document_number' => 'TRV-202610-0007', 'actor' => 'AZHAR', 'step' => 'hod'],
        ]))->handle();

        Mail::assertQueued(WorkflowMail::class, function (WorkflowMail $mail) {
            $payload = $mail->payload;

            return $payload['title'] === 'Pengajuan perjalanan dinas diajukan'
                && $payload['meta']['document_number'] === 'TRV-202610-0007'
                && $payload['meta']['actor'] === 'AZHAR'
                && str_starts_with($payload['action_url'], config('app.url'));
        });
        Mail::assertQueued(WorkflowMail::class, 1);
    }

    public function test_job_skips_user_who_opted_out(): void
    {
        Mail::fake();

        $user = $this->userWithEmail();
        $user->forceFill(['email_notifications' => false])->save();

        (new SendWorkflowEmail($user->id, $this->payload()))->handle();

        Mail::assertNothingQueued();
    }

    public function test_job_skips_low_value_events_from_blacklist(): void
    {
        Mail::fake();

        $user = $this->userWithEmail();

        (new SendWorkflowEmail($user->id, ['event' => 'approval.step_deferred', 'title' => 't', 'message' => 'm', 'url' => '/approvals/1', 'meta' => []]))->handle();

        Mail::assertNothingQueued();
    }

    public function test_job_skips_users_without_email_or_dummy_email(): void
    {
        Mail::fake();

        $noEmail = User::factory()->create();
        $noEmail->forceFill(['email' => ''])->save();

        $dummyDomain = $this->userWithEmail();
        $dummyDomain->forceFill(['email' => 'demo.owner@demo.eform-bp.invalid'])->save();

        (new SendWorkflowEmail($noEmail->id, $this->payload()))->handle();
        (new SendWorkflowEmail($dummyDomain->id, $this->payload()))->handle();

        Mail::assertNothingQueued();
    }

    public function test_job_skips_when_killed_by_config_switch(): void
    {
        Mail::fake();
        config()->set('eform.mail.enabled', false);

        $user = $this->userWithEmail();

        (new SendWorkflowEmail($user->id, $this->payload()))->handle();

        Mail::assertNothingQueued();
    }

    private function userWithEmail(): User
    {
        return User::factory()->create(['email' => 'pemohon@borneoprima.com']);
    }

    /**
     * @return array{event: string, title: string, message: string, url: string, meta: array}
     */
    private function payload(): array
    {
        return [
            'event' => 'leave.submitted',
            'title' => 'Pengajuan cuti diajukan',
            'message' => 'Pengajuan cuti membawa kondisi baru.',
            'url' => route('dashboard'),
            'meta' => ['module' => 'Pengajuan cuti/izin', 'document_number' => 'LV-202610-0002', 'actor' => 'AZHAR'],
        ];
    }
}
