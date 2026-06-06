<?php

namespace Tests\Feature;

use App\Models\UserInvitation;
use App\Panel\Conference\Livewire\UserInvitationTable;
use Tests\TestCase;

class UserInvitationTableTest extends TestCase
{
    public function test_copy_link_handler_sends_success_feedback_and_closes_dropdown_after_writing_to_clipboard(): void
    {
        $invitation = new UserInvitation([
            'email' => 'invited-user@example.test',
            'role_name' => 'Conference Manager',
            'token' => 'copy-feedback-token',
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $component = new class extends UserInvitationTable
        {
            public function copyLinkClickHandler(UserInvitation $record): string
            {
                return $this->getCopyLinkClickHandler($record);
            }
        };

        $handler = $component->copyLinkClickHandler($invitation);

        $this->assertStringContainsString('window.navigator.clipboard.writeText', $handler);
        $this->assertStringContainsString('copy-feedback-token', $handler);
        $this->assertStringContainsString('FilamentNotification', $handler);
        $this->assertStringContainsString('Invitation link copied.', $handler);
        $this->assertStringContainsString('success()', $handler);
        $this->assertStringContainsString('send()', $handler);
        $this->assertStringContainsString('close()', $handler);
    }

    public function test_copy_link_handler_falls_back_when_clipboard_api_is_unavailable_or_rejected(): void
    {
        $invitation = new UserInvitation([
            'email' => 'invited-user@example.test',
            'role_name' => 'Conference Manager',
            'token' => 'copy-feedback-token',
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        $component = new class extends UserInvitationTable
        {
            public function copyLinkClickHandler(UserInvitation $record): string
            {
                return $this->getCopyLinkClickHandler($record);
            }
        };

        $handler = $component->copyLinkClickHandler($invitation);

        $this->assertStringContainsString('window.navigator.clipboard && window.navigator.clipboard.writeText', $handler);
        $this->assertStringContainsString('.catch(() => fallbackCopy())', $handler);
        $this->assertStringContainsString("document.execCommand('copy')", $handler);
        $this->assertStringContainsString('Unable to copy invitation link.', $handler);
        $this->assertStringContainsString('danger()', $handler);
    }
}
