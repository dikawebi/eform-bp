<?php

namespace Tests\Feature;

use App\Jobs\SendWorkflowEmail;
use App\Mail\WorkflowMail;
use App\Models\MailSetting;
use App\Models\User;
use App\Services\MailSettingsService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailSettingsUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesPermissionsSeeder::class);
    }

    public function test_admin_can_open_page_but_employee_cannot(): void
    {
        $admin = $this->admin();
        $employee = $this->employee();

        $this->actingAs($admin)->get(route('settings.mail.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/Mail/Index')->has('settings')->has('hasPassword')->has('hasRow'));
        $this->actingAs($employee)->get(route('settings.mail.index'))->assertForbidden();
    }

    public function test_update_persists_settings_encrypts_password_and_audits(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('settings.mail.update'), [
            'mailer' => 'smtp',
            'host' => 'smtp.borneoprima.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'admin.it@borneoprima.com',
            'password' => 'sgR4h4s14t',
            'from_address' => 'admin.it@borneoprima.com',
            'from_name' => 'eForm BP',
            'notifications_enabled' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $row = MailSetting::query()->firstOrFail();
        $this->assertSame('smtp.borneoprima.com', $row->host);
        $this->assertSame(587, $row->port);
        $this->assertSame('sgR4h4s14t', $row->password);
        $rawPassword = DB::table('mail_settings')->value('password');
        $this->assertNotSame('sgR4h4s14t', $rawPassword);
        $this->assertTrue(DB::table('activity_log')->where('description', 'mail.settings.updated')->where('subject_id', $row->id)->exists());

        // Password tidak pernah dipasang ke UI; admin tetap dapatnya.
        $this->actingAs($admin)->get(route('settings.mail.index'))
            ->assertInertia(fn ($page) => $page->where('hasPassword', true)
                ->where('settings.mailer', 'smtp')
                ->missing('settings.password'));
    }

    public function test_update_dropdown_validation_and_blank_password_keeps_old(): void
    {
        $admin = $this->admin();
        MailSetting::create(['mailer' => 'smtp', 'host' => 'smtp1.borneoprima.com', 'port' => 465, 'encryption' => 'tls', 'username' => 'admin.it@borneoprima.com', 'password' => 'pass1', 'from_address' => 'admin.it@borneoprima.com', 'from_name' => 'eForm BP']);

        $this->actingAs($admin)->put(route('settings.mail.update'), ['mailer' => 'smtp', 'host' => 'smtp1.borneoprima.com', 'port' => 465, 'encryption' => 'tls', 'username' => 'admin.it@borneoprima.com', 'password' => 'pass1', 'from_address' => 'admin.it@borneoprima.com', 'from_name' => 'eForm BP', 'notifications_enabled' => true])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('settings.mail.update'), ['mailer' => 'xcustom', 'host' => null, 'port' => null, 'encryption' => null, 'username' => null, 'password' => null, 'from_address' => null, 'from_name' => null])->assertSessionHasErrors(['mailer']);
        $row = MailSetting::query()->firstOrFail();
        $this->assertSame('smtp1.borneoprima.com', $row->host);
        $this->assertSame('pass1', $row->password);
    }

    public function test_apply_switches_runtime_config_from_database(): void
    {
        MailSetting::create(['mailer' => 'smtp', 'host' => 'db-host.borneoprima.com', 'port' => 465, 'encryption' => 'ssl', 'username' => 'admin.it@borneoprima.com', 'password' => 'secret-vault', 'from_address' => 'pengirim@borneoprima.com', 'from_name' => 'eForm Dinas', 'notifications_enabled' => true]);

        MailSettingsService::apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('db-host.borneoprima.com', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('ssl', config('mail.mailers.smtp.encryption'));
        $this->assertSame('pengirim@borneoprima.com', config('mail.from.address'));
        $this->assertSame('eForm Dinas', config('mail.from.name'));
        $this->assertTrue(MailSettingsService::emailEnabled());

        // Switch di halaman settings mematikan email bersama-sama env kill-switch.
        MailSetting::query()->update(['notifications_enabled' => false]);
        config()->set('eform.mail.enabled', true);
        $this->assertFalse(MailSettingsService::emailEnabled());
    }

    public function test_send_test_email_and_failure_feedback(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('settings.mail.test'), ['test_address' => 'admin.test@borneoprima.com'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Email percobaan terkirim ke admin.test@borneoprima.com. Cek folder inbox/spam.');

        Mail::assertQueued(WorkflowMail::class, function (WorkflowMail $mail) {
            return $mail->payload['event'] === 'mail.test'
                && str_contains($mail->payload['title'], 'percobaan');
        });
        $this->assertTrue(DB::table('activity_log')->where('description', 'mail.settings.test_sent')->exists());
    }

    public function test_job_skips_when_disabled_in_settings(): void
    {
        Mail::fake();
        MailSetting::create(['notifications_enabled' => false]);
        $user = User::factory()->create(['email' => 'pemohon@borneoprima.com']);

        (new SendWorkflowEmail($user->id, ['event' => 'it.submitted', 'title' => 't', 'message' => 'm', 'url' => '/it-requests/1', 'meta' => []]))->handle();

        Mail::assertNothingQueued();
    }

    public function test_job_applies_database_smtp_before_sending(): void
    {
        Mail::fake();
        MailSetting::create(['mailer' => 'smtp', 'host' => 'db-host.borneoprima.com', 'port' => 465, 'encryption' => 'ssl', 'notifications_enabled' => true]);
        $user = User::factory()->create(['email' => 'pemohon@borneoprima.com']);

        (new SendWorkflowEmail($user->id, ['event' => 'it.submitted', 'title' => 't', 'message' => 'm', 'url' => '/it-requests/1', 'meta' => []]))->handle();

        Mail::assertQueued(WorkflowMail::class, 1);
        $this->assertSame('db-host.borneoprima.com', config('mail.mailers.smtp.host') ?? config('mail.mailers.smtp.host'));
    }

    private function admin(): User
    {
        $worker = User::factory()->create();
        $worker->assignRole('admin');

        return $worker;
    }

    private function employee(): User
    {
        $worker = User::factory()->create();
        $worker->assignRole('employee');

        return $worker;
    }
}
