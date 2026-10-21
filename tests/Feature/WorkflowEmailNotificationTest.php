<?php

namespace Tests\Feature;

use App\Enums\ApprovalStepStatus;
use App\Enums\RequestStatus;
use App\Jobs\SendWorkflowEmail;
use App\Mail\WorkflowMail;
use App\Models\ApprovalRequest;
use App\Models\EmailTemplate;
use App\Models\Employee;
use App\Models\TravelRequest;
use App\Models\User;
use App\Services\InAppNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

    public function test_job_renders_template_placeholders_with_approval_data(): void
    {
        Mail::fake();

        EmailTemplate::query()->create([
            'module' => null,
            'event' => 'approval.assigned',
            'subject' => 'Approval {nomor_dokumen} tahap {tahap}',
            'message' => 'Dari {pelaku} untuk {modul}. {judul}',
            'subject_en' => 'Approval {document_number} stage {step}',
            'message_en' => 'From {actor} for {module}.',
        ]);

        $user = $this->userWithEmail();

        (new SendWorkflowEmail($user->id, [
            'event' => 'approval.assigned',
            'title' => 'Judul asli',
            'message' => 'Pesan asli',
            'url' => '/approvals/9',
            'meta' => ['module' => 'Pengajuan cuti/izin', 'document_number' => 'LV-1', 'actor' => 'Budi', 'step' => 'hod'],
        ]))->handle();

        Mail::assertQueued(WorkflowMail::class, function (WorkflowMail $mail) {
            return $mail->payload['title'] === 'Approval LV-1 tahap HOD'
                && $mail->payload['message'] === 'Dari Budi untuk Pengajuan cuti/izin. Judul asli';
        });
        Mail::assertQueued(WorkflowMail::class, 1);
    }

    public function test_job_includes_approval_progress_from_document_chain(): void
    {
        Mail::fake();

        $owner = $this->userWithEmail();
        $employee = Employee::factory()->create(['user_id' => $owner->id]);
        $hod = User::factory()->create(['name' => 'HOD Satu']);
        $it = User::factory()->create(['name' => 'IT Satu']);
        $pm = User::factory()->create(['name' => 'PM Satu']);

        $travel = new TravelRequest;
        $travel->forceFill([
            'request_number' => 'DINAS-PROGRESS-001', 'employee_id' => $employee->id, 'purpose' => 'Progress',
            'start_date' => '2026-10-01', 'end_date' => '2026-10-02', 'origin' => 'A', 'destination' => 'B',
            'status' => RequestStatus::InReview, 'created_by' => $owner->id,
        ])->save();

        ApprovalRequest::create([
            'approvable_type' => $travel->getMorphClass(), 'approvable_id' => $travel->id,
            'chain_generation' => 'progress', 'step_order' => 1, 'step_code' => 'hod', 'approver_role' => 'hod',
            'approver_user_id' => $hod->id, 'status' => ApprovalStepStatus::Approved, 'acted_at' => now(),
        ]);
        ApprovalRequest::create([
            'approvable_type' => $travel->getMorphClass(), 'approvable_id' => $travel->id,
            'chain_generation' => 'progress', 'step_order' => 2, 'step_code' => 'it', 'approver_role' => 'it',
            'approver_user_id' => $it->id, 'status' => ApprovalStepStatus::Pending,
        ]);
        ApprovalRequest::create([
            'approvable_type' => $travel->getMorphClass(), 'approvable_id' => $travel->id,
            'chain_generation' => 'progress', 'step_order' => 3, 'step_code' => 'pm_gm', 'approver_role' => 'pm_gm',
            'approver_user_id' => $pm->id, 'status' => ApprovalStepStatus::Cancelled,
        ]);

        (new SendWorkflowEmail($owner->id, [
            'event' => 'approval.assigned',
            'title' => 'Approval baru',
            'message' => 'Menunggu pemeriksaan.',
            'url' => route('travels.show', $travel),
            'meta' => InAppNotifier::context($travel, $hod, 'it'),
        ]))->handle();

        Mail::assertQueued(WorkflowMail::class, function (WorkflowMail $mail): bool {
            $progress = $mail->payload['progress'];

            return count($progress) === 3
                && $progress[0]['label'] === 'HOD'
                && $progress[0]['status'] === 'approved'
                && $progress[1]['label'] === 'IT'
                && $progress[1]['status'] === 'pending'
                && $progress[2]['label'] === 'PM/GM'
                && $progress[2]['status'] === 'pending';
        });
    }

    public function test_mail_view_renders_approval_progress_stepper(): void
    {
        $mail = new WorkflowMail([
            'event' => 'approval.assigned',
            'title' => 'Approval baru',
            'message' => 'Menunggu pemeriksaan.',
            'meta' => [],
            'progress' => [
                ['code' => 'hod', 'label' => 'HOD', 'status' => 'approved', 'approver' => 'HOD Satu', 'acted_at' => '21 Okt 2026'],
                ['code' => 'it', 'label' => 'IT', 'status' => 'pending', 'approver' => 'IT Satu', 'acted_at' => null],
            ],
            'action_url' => 'https://example.test/approvals/1',
            'company' => ['name' => 'PT Borneo Prima', 'address' => 'Balikpapan'],
        ]);

        $html = $mail->render();

        $this->assertStringContainsString('Progress persetujuan', $html);
        $this->assertStringContainsString('1 dari 2 tahap disetujui', $html);
        $this->assertStringContainsString('HOD', $html);
        $this->assertStringContainsString('Disetujui', $html);
        $this->assertStringContainsString('Menunggu', $html);
    }

    public function test_mail_attaches_stored_pdf_from_private_disk(): void
    {
        Storage::fake('eform-private');
        Storage::disk('eform-private')->put('it-requests/pr-ready/ITR-TEST-1.pdf', '%PDF-1.4 test');

        $mail = new WorkflowMail([
            'event' => 'it.pr_ready',
            'title' => 'Approval selesai',
            'message' => 'Lanjutkan pembuatan PR.',
            'meta' => [],
            'attachments' => [[
                'disk' => 'eform-private',
                'path' => 'it-requests/pr-ready/ITR-TEST-1.pdf',
                'name' => 'IT-Request-ITR-TEST-1.pdf',
                'mime' => 'application/pdf',
            ]],
            'action_url' => 'https://example.test/it-requests/1',
            'company' => ['name' => 'PT Borneo Prima', 'address' => 'Balikpapan'],
        ]);

        $attachments = $mail->attachments();

        $this->assertCount(1, $attachments);
        $this->assertSame('IT-Request-ITR-TEST-1.pdf', $attachments[0]->as);
        $this->assertSame('application/pdf', $attachments[0]->mime);
    }

    public function test_mail_view_embeds_logo_as_inline_attachment(): void
    {
        $mail = new WorkflowMail([
            'event' => 'approval.assigned',
            'title' => 'Judul',
            'message' => 'Pesan',
            'meta' => [],
            'action_url' => 'https://example.test/approvals/1',
            'company' => ['name' => 'PT Borneo Prima', 'address' => 'Balikpapan'],
        ]);

        $html = $mail->render();

        // Mailer::render() mengganti CID inline dengan data URI untuk pratinjau;
        // keberadaan data URI membuktikan logo di-embed ke pesan, bukan URL eksternal.
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringNotContainsString('images/logo-bp.svg', $html);
        $this->assertStringNotContainsString('localhost', $html);
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
