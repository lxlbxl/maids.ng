<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\NotificationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected $employer;
    protected $maid;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupRoles();

        $this->employer = User::factory()->create(['role' => 'employer']);
        $this->employer->assignRole('employer');

        $this->maid = User::factory()->create(['role' => 'maid']);
        $this->maid->assignRole('maid');

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
    }

    public function test_user_can_view_their_notifications()
    {
        NotificationLog::create([
            'user_id' => $this->employer->id,
            'user_type' => 'employer',
            'notification_type' => 'assignment_created',
            'channel' => 'email',
            'subject' => 'New Assignment',
            'content' => 'You have a new assignment',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->employer)
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.0.subject', 'New Assignment');
    }

    public function test_user_can_view_unread_notification_count()
    {
        NotificationLog::create([
            'user_id' => $this->employer->id,
            'user_type' => 'employer',
            'notification_type' => 'assignment_created',
            'channel' => 'email',
            'subject' => 'New Assignment',
            'content' => 'You have a new assignment',
            'status' => 'sent',
            'sent_at' => now(),
            'read_at' => null,
        ]);

        NotificationLog::create([
            'user_id' => $this->employer->id,
            'user_type' => 'employer',
            'notification_type' => 'salary_reminder',
            'channel' => 'email',
            'subject' => 'Salary Due',
            'content' => 'Salary is due soon',
            'status' => 'sent',
            'sent_at' => now(),
            'read_at' => now(),
        ]);

        $response = $this->actingAs($this->employer)
            ->getJson('/api/v1/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 1
                ]
            ]);
    }

    public function test_user_can_mark_notification_as_read()
    {
        $notification = NotificationLog::create([
            'user_id' => $this->employer->id,
            'user_type' => 'employer',
            'notification_type' => 'assignment_created',
            'channel' => 'email',
            'subject' => 'New Assignment',
            'content' => 'You have a new assignment',
            'status' => 'sent',
            'sent_at' => now(),
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->employer)
            ->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('notification_logs', [
            'id' => $notification->id,
            'read_at' => now()->format('Y-m-d'),
        ]);
    }

    public function test_user_can_mark_all_notifications_as_read()
    {
        NotificationLog::create([
            'user_id' => $this->employer->id,
            'user_type' => 'employer',
            'notification_type' => 'assignment_created',
            'channel' => 'email',
            'subject' => 'New Assignment',
            'content' => 'You have a new assignment',
            'status' => 'sent',
            'sent_at' => now(),
            'read_at' => null,
        ]);

        NotificationLog::create([
            'user_id' => $this->employer->id,
            'user_type' => 'employer',
            'notification_type' => 'salary_reminder',
            'channel' => 'email',
            'subject' => 'Salary Due',
            'content' => 'Salary is due soon',
            'status' => 'sent',
            'sent_at' => now(),
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->employer)
            ->postJson('/api/v1/notifications/mark-all-read');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $unreadCount = NotificationLog::where('user_id', $this->employer->id)
            ->whereNull('read_at')
            ->count();

        $this->assertEquals(0, $unreadCount);
    }

    public function test_user_can_delete_notification()
    {
        $notification = NotificationLog::create([
            'user_id' => $this->employer->id,
            'user_type' => 'employer',
            'notification_type' => 'assignment_created',
            'channel' => 'email',
            'subject' => 'New Assignment',
            'content' => 'You have a new assignment',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->employer)
            ->deleteJson("/api/v1/notifications/{$notification->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('notification_logs', [
            'id' => $notification->id,
        ]);
    }

    public function test_user_cannot_view_other_users_notifications()
    {
        $otherEmployer = User::factory()->create(['role' => 'employer']);
        $otherEmployer->assignRole('employer');

        $notification = NotificationLog::create([
            'user_id' => $otherEmployer->id,
            'user_type' => 'employer',
            'notification_type' => 'assignment_created',
            'channel' => 'email',
            'subject' => 'New Assignment',
            'content' => 'You have a new assignment',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->employer)
            ->getJson("/api/v1/notifications/{$notification->id}");

        $response->assertStatus(404);
    }

    public function test_admin_can_view_notification_report()
    {
        NotificationLog::create([
            'user_id' => $this->employer->id,
            'user_type' => 'employer',
            'notification_type' => 'assignment_created',
            'channel' => 'email',
            'subject' => 'New Assignment',
            'content' => 'You have a new assignment',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        NotificationLog::create([
            'user_id' => $this->maid->id,
            'user_type' => 'maid',
            'notification_type' => 'salary_paid',
            'channel' => 'sms',
            'subject' => 'Salary Paid',
            'content' => 'Your salary has been paid',
            'status' => 'delivered',
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/reports/notifications');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_notification_model_methods()
    {
        $notification = NotificationLog::create([
            'user_id' => $this->employer->id,
            'user_type' => 'employer',
            'notification_type' => 'assignment_created',
            'channel' => 'email',
            'subject' => 'New Assignment',
            'content' => 'You have a new assignment',
            'status' => 'pending',
            'scheduled_at' => now()->addHour(),
        ]);

        // Test isPending
        $this->assertTrue($notification->isPending());
        $this->assertFalse($notification->isSent());
        $this->assertFalse($notification->isDelivered());
        $this->assertFalse($notification->isRead());

        // Mark as sent
        $notification->markAsSent();
        $notification->refresh();
        $this->assertTrue($notification->isSent());

        // Mark as delivered
        $notification->markAsDelivered(['message_id' => '12345']);
        $notification->refresh();
        $this->assertTrue($notification->isDelivered());

        // Mark as read
        $notification->markAsRead();
        $notification->refresh();
        $this->assertTrue($notification->isRead());
    }

    public function test_notification_follow_up_creation()
    {
        $parentNotification = NotificationLog::create([
            'user_id' => $this->employer->id,
            'user_type' => 'employer',
            'notification_type' => 'salary_reminder',
            'channel' => 'email',
            'subject' => 'Salary Due Soon',
            'content' => 'Your salary is due in 3 days',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $followUp = $parentNotification->scheduleFollowUp(1, now()->addDays(2));

        $this->assertNotNull($followUp);
        $this->assertEquals($parentNotification->id, $followUp->parent_notification_id);
        $this->assertEquals(1, $followUp->follow_up_sequence);
        $this->assertEquals('scheduled', $followUp->status);
    }
}
