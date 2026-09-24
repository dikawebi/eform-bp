<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WorkflowNotification;
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
