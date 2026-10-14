<?php

namespace Tests\Feature;

use App\Models\ErpRequest;
use App\Models\ItRequest;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\InAppNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_inbox_shares_recent_items_and_unread_count(): void
    {
        $user = User::factory()->create();
        $user->notify(new WorkflowNotification('approval.assigned', 'Approval baru', 'Ada pengajuan untuk diperiksa.', '/approvals/10'));

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('unreadNotifications', 1)
                ->has('notifications', 1)
                ->where('notifications.0.title', 'Approval baru')
                ->where('notifications.0.url', '/approvals/10'));
    }

    public function test_notification_shares_event_and_meta_detail(): void
    {
        $user = User::factory()->create();
        $user->notify(new WorkflowNotification('approval.assigned', 'Approval baru', 'Periksa IT-2026-0001.', '/approvals/10', [
            'module' => 'IT Request', 'document_number' => 'IT-2026-0001', 'actor' => 'Budi', 'step' => 'it',
        ]));

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('notifications.0.event', 'approval.assigned')
                ->where('notifications.0.meta.module', 'IT Request')
                ->where('notifications.0.meta.document_number', 'IT-2026-0001')
                ->where('notifications.0.meta.actor', 'Budi')
                ->where('notifications.0.meta.step', 'it'));
    }

    public function test_it_and_erp_notification_urls_point_to_pending_data(): void
    {
        $notifier = app(InAppNotifier::class);

        $it = new ItRequest();
        $it->id = 99;
        $this->assertStringEndsWith('/it-requests/99', $notifier->url($it));
        $this->assertSame('IT Request', $notifier->label($it));

        $erp = new ErpRequest();
        $erp->id = 77;
        $this->assertStringEndsWith('/erp-requests/77', $notifier->url($erp));
        $this->assertSame('ERP Request', $notifier->label($erp));

        $context = InAppNotifier::context($it, null, 'it');
        $this->assertSame('IT Request', $context['module']);
        $this->assertSame('it', $context['step']);
    }

    public function test_absolute_notification_url_resolves_to_pending_data_path(): void
    {
        $user = User::factory()->create();
        $user->notify(new WorkflowNotification('approval.assigned', 'Approval baru', 'Periksa data.', 'http://localhost/it-requests/99'));

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('notifications.0.url', '/it-requests/99'));
    }

    public function test_user_can_only_mark_their_own_notifications_as_read(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owner->notify(new WorkflowNotification('approval.assigned', 'Milik saya', 'Pesan.', '/approvals/1'));
        $other->notify(new WorkflowNotification('approval.assigned', 'Milik orang lain', 'Pesan.', '/approvals/2'));
        $ownNotification = $owner->notifications()->firstOrFail();
        $otherNotification = $other->notifications()->firstOrFail();

        $this->actingAs($owner)->post(route('notifications.read', $otherNotification->id))->assertRedirect();
        $this->assertNull($otherNotification->fresh()->read_at);
        $this->assertNull($ownNotification->fresh()->read_at);

        $this->post(route('notifications.read', $ownNotification->id))->assertRedirect();
        $this->assertNotNull($ownNotification->fresh()->read_at);
    }
}
