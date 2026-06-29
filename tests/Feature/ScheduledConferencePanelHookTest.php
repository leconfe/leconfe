<?php

namespace Tests\Feature;

use App\Facades\Hook;
use App\Models\Conference;
use App\Models\ScheduledConference;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Pages\ParticipantRegistration;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\CreateSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledConferencePanelHookTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach ($this->hookNames() as $hook) {
            Hook::clear($hook);
        }

        parent::tearDown();
    }

    public function test_scheduled_conference_panel_output_hooks_render_registered_content(): void
    {
        $context = $this->makeScheduledConferenceContext();

        Hook::add('Panel::ScheduledConference::TopbarAfterTitle', function ($hookName, &$output): void {
            $output .= '<span data-test="billing-topbar">Gold plan</span>';
        });

        Hook::add('Panel::ScheduledConference::TenantMenuAfterCurrentTitle', function ($hookName, &$output): void {
            $output .= '<span data-test="billing-tenant-menu">Gold</span>';
        });

        Hook::add('Panel::ScheduledConference::DashboardOverviewBefore', function ($hookName, &$output): void {
            $output .= '<section data-test="billing-dashboard-before">Billing summary</section>';
        });

        Hook::add('Panel::ScheduledConference::DashboardOverviewAfter', function ($hookName, &$output): void {
            $output .= '<section data-test="billing-dashboard-after">Billing after</section>';
        });

        $topbar = view('panel.scheduledConference.hooks.topbar')->render();
        $this->assertStringContainsString('data-test="billing-topbar"', $topbar);

        $tenantMenu = view('panel.scheduledConference.hooks.sidebar-nav-start', [
            'currentConference' => $context['conference'],
            'currentScheduledConference' => $context['scheduledConference'],
            'scheduledConferences' => collect(),
        ])->render();
        $this->assertStringContainsString('data-test="billing-tenant-menu"', $tenantMenu);

        $overviewView = file_get_contents(resource_path('views/panel/scheduledConference/widgets/overview.blade.php'));

        $this->assertStringContainsString("@hook('Panel::ScheduledConference::DashboardOverviewBefore')", $overviewView);
        $this->assertStringContainsString("@hook('Panel::ScheduledConference::DashboardOverviewAfter')", $overviewView);
    }

    public function test_submission_create_availability_hook_can_close_form_with_custom_message(): void
    {
        $this->makeScheduledConferenceContext();

        Hook::add('Panel::ScheduledConference::SubmissionCreate::availability', function ($hookName, &$isOpen, &$closedMessage, $page): void {
            $isOpen = false;
            $closedMessage = 'Submission limit reached for the Free plan.';
        });

        $state = $this->getSubmissionCreateViewData(new CreateSubmission);

        $this->assertFalse($state['isOpen']);
        $this->assertSame('Submission limit reached for the Free plan.', $state['closedMessage']);

        $submissionView = file_get_contents(resource_path('views/panel/conference/resources/submission-resource/pages/create-submission.blade.php'));

        $this->assertStringContainsString('$closedMessage', $submissionView);
    }

    public function test_participant_registration_availability_hook_can_close_form_with_custom_message(): void
    {
        $this->makeScheduledConferenceContext();

        Hook::add('Panel::ScheduledConference::ParticipantRegistration::availability', function ($hookName, &$isOpen, &$closedMessage, $page): void {
            $isOpen = false;
            $closedMessage = 'Participant limit reached for the Free plan.';
        });

        $state = $this->getParticipantRegistrationViewData(new ParticipantRegistration);

        $this->assertFalse($state['isOpen']);
        $this->assertSame('Participant limit reached for the Free plan.', $state['closedMessage']);
    }

    public function test_availability_hooks_do_not_change_default_state_when_unregistered(): void
    {
        $this->makeScheduledConferenceContext();

        $submissionState = $this->getSubmissionCreateViewData(new CreateSubmission);
        $participantState = $this->getParticipantRegistrationViewData(new ParticipantRegistration);

        $this->assertArrayHasKey('isOpen', $submissionState);
        $this->assertArrayHasKey('closedMessage', $submissionState);
        $this->assertArrayHasKey('isOpen', $participantState);
        $this->assertArrayHasKey('closedMessage', $participantState);
    }

    protected function makeScheduledConferenceContext(): array
    {
        $conference = Conference::query()->create([
            'name' => 'Hook Conference',
            'path' => 'hook-conference',
        ]);

        $scheduledConference = ScheduledConference::withoutGlobalScopes()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Hook Scheduled Conference',
            'path' => 'hook-2026',
            'date_start' => now()->toDateString(),
            'date_end' => now()->addDays(3)->toDateString(),
            'is_published' => true,
        ]);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());

        $track = Track::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'title' => 'Main Track',
            'abbreviation' => 'MAIN',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'password' => 'password',
        ]);

        return compact('conference', 'scheduledConference', 'track', 'user');
    }

    protected function getSubmissionCreateViewData(CreateSubmission $page): array
    {
        return (function (): array {
            return $this->getViewData();
        })->call($page);
    }

    protected function getParticipantRegistrationViewData(ParticipantRegistration $page): array
    {
        return (function (): array {
            return $this->getViewData();
        })->call($page);
    }

    protected function hookNames(): array
    {
        return [
            'Panel::ScheduledConference::TopbarAfterTitle',
            'Panel::ScheduledConference::TenantMenuAfterCurrentTitle',
            'Panel::ScheduledConference::DashboardOverviewBefore',
            'Panel::ScheduledConference::DashboardOverviewAfter',
            'Panel::ScheduledConference::SubmissionCreate::availability',
            'Panel::ScheduledConference::ParticipantRegistration::availability',
        ];
    }
}
