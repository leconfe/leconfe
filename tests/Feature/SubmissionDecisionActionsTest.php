<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Submissions\CallforAbstract;
use App\Panel\ScheduledConference\Livewire\Submissions\PeerReview;
use App\Panel\ScheduledConference\Livewire\Submissions\Presentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class SubmissionDecisionActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test/submissions/{record}', fn () => null)
            ->name('filament.conference.resources.submissions.view');

        Route::get('/test/submissions/{record}/review', fn () => null)
            ->name('filament.conference.resources.submissions.review');
    }

    public function test_call_for_abstract_change_decision_shows_the_current_review_decision(): void
    {
        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnReview,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(CallforAbstract::class, ['submission' => $context['submission']])
            ->assertSee(__('general.send_for_review'))
            ->assertSee(__('general.skip_review'))
            ->assertSee(__('general.decline'));
    }

    public function test_call_for_abstract_change_decision_shows_all_decisions_after_skipping_to_presentation(): void
    {
        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::Presentation,
            'status' => SubmissionStatus::OnPresentation,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(CallforAbstract::class, ['submission' => $context['submission']])
            ->assertSee(__('general.send_for_review'))
            ->assertSee(__('general.skip_review'))
            ->assertSee(__('general.decline'));
    }

    public function test_call_for_abstract_change_decision_shows_all_decisions_after_sending_to_editing(): void
    {
        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::Editing,
            'status' => SubmissionStatus::Editing,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(CallforAbstract::class, ['submission' => $context['submission']])
            ->assertSee(__('general.send_for_review'))
            ->assertSee(__('general.skip_review'))
            ->assertSee(__('general.decline'));
    }

    public function test_call_for_abstract_change_decision_shows_the_current_decline_decision(): void
    {
        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::CallforAbstract,
            'status' => SubmissionStatus::Declined,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(CallforAbstract::class, ['submission' => $context['submission']])
            ->assertSee(__('general.send_for_review'))
            ->assertSee(__('general.skip_review'))
            ->assertSee(__('general.decline'));
    }

    public function test_peer_review_change_decision_shows_the_current_accept_decision(): void
    {
        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::Presentation,
            'status' => SubmissionStatus::OnPresentation,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(PeerReview::class, ['submission' => $context['submission']])
            ->assertSee(__('general.request_revision'))
            ->assertSee(__('general.accept_submission'))
            ->assertSee(__('general.decline_submission'));
    }

    public function test_peer_review_change_decision_shows_the_current_decline_decision(): void
    {
        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::Declined,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(PeerReview::class, ['submission' => $context['submission']])
            ->assertSee(__('general.request_revision'))
            ->assertSee(__('general.accept_submission'))
            ->assertSee(__('general.decline_submission'));
    }

    public function test_peer_review_change_decision_shows_all_decisions_after_sending_to_editing(): void
    {
        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::Editing,
            'status' => SubmissionStatus::Editing,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(PeerReview::class, ['submission' => $context['submission']])
            ->assertSee(__('general.request_revision'))
            ->assertSee(__('general.accept_submission'))
            ->assertSee(__('general.decline_submission'));
    }

    public function test_presentation_change_decision_shows_the_current_editing_decision(): void
    {
        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::Editing,
            'status' => SubmissionStatus::Editing,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(Presentation::class, ['submission' => $context['submission']])
            ->assertSee(__('general.send_to_editing'));
    }

    public function test_presentation_change_decision_shows_editing_decision_after_decline_at_presentation_stage(): void
    {
        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::Presentation,
            'status' => SubmissionStatus::Declined,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(Presentation::class, ['submission' => $context['submission']])
            ->assertSee(__('general.send_to_editing'));
    }

    public function test_call_for_abstract_decision_can_move_editing_submission_back_to_review(): void
    {
        Mail::fake();

        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::Editing,
            'status' => SubmissionStatus::Editing,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(CallforAbstract::class, ['submission' => $context['submission']])
            ->callAction('accept', data: $this->callForAbstractNotificationData([
                'papers' => [],
            ]));

        $context['submission']->refresh();

        $this->assertSame(SubmissionStage::PeerReview, $context['submission']->stage);
        $this->assertSame(SubmissionStatus::OnReview, $context['submission']->status);
    }

    public function test_call_for_abstract_decision_can_skip_editing_submission_to_presentation(): void
    {
        Mail::fake();

        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::Editing,
            'status' => SubmissionStatus::Editing,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(CallforAbstract::class, ['submission' => $context['submission']])
            ->callAction('acceptAndSkipReview', data: $this->paperNotificationData());

        $context['submission']->refresh();

        $this->assertSame(SubmissionStage::Presentation, $context['submission']->stage);
        $this->assertSame(SubmissionStatus::OnPresentation, $context['submission']->status);
        $this->assertTrue($context['submission']->skipped_review);
    }

    public function test_peer_review_decision_can_request_revision_from_presentation(): void
    {
        Mail::fake();

        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::Presentation,
            'status' => SubmissionStatus::OnPresentation,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(PeerReview::class, ['submission' => $context['submission']])
            ->callAction('requestRevisionAction', data: $this->paperNotificationData());

        $context['submission']->refresh();

        $this->assertSame(SubmissionStage::PeerReview, $context['submission']->stage);
        $this->assertSame(SubmissionStatus::OnReview, $context['submission']->status);
        $this->assertTrue($context['submission']->revision_required);
    }

    public function test_peer_review_decision_can_move_editing_submission_back_to_presentation(): void
    {
        Mail::fake();

        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::Editing,
            'status' => SubmissionStatus::Editing,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(PeerReview::class, ['submission' => $context['submission']])
            ->callAction('acceptSubmissionAction', data: $this->paperNotificationData());

        $context['submission']->refresh();

        $this->assertSame(SubmissionStage::Presentation, $context['submission']->stage);
        $this->assertSame(SubmissionStatus::OnPresentation, $context['submission']->status);
    }

    public function test_presentation_decision_can_move_declined_submission_to_editing(): void
    {
        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::Presentation,
            'status' => SubmissionStatus::Declined,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(Presentation::class, ['submission' => $context['submission']])
            ->callAction('sendToEditing');

        $context['submission']->refresh();

        $this->assertSame(SubmissionStage::Editing, $context['submission']->stage);
        $this->assertSame(SubmissionStatus::Editing, $context['submission']->status);
    }

    protected function callForAbstractNotificationData(array $overrides = []): array
    {
        return [
            'subject' => 'Decision update',
            'message' => 'Decision update message',
            'no-notification' => true,
            ...$overrides,
        ];
    }

    protected function paperNotificationData(array $overrides = []): array
    {
        return [
            'email' => 'author@example.test',
            'subject' => 'Decision update',
            'message' => 'Decision update message',
            'do-not-notify-author' => true,
            ...$overrides,
        ];
    }

    protected function makeEditorSubmissionContext(array $submissionState): array
    {
        $conference = Conference::query()->create([
            'name' => 'Conference',
            'path' => 'conference',
        ]);

        $scheduledConference = ScheduledConference::withoutGlobalScopes()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Scheduled Conference',
            'path' => 'scheduled-conference',
            'date_start' => now()->toDateString(),
            'date_end' => now()->addDays(2)->toDateString(),
        ]);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());

        $track = Track::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'title' => 'Track',
            'abbreviation' => 'TRK',
            'is_active' => true,
        ]);

        $author = User::query()->create([
            'given_name' => 'Author',
            'family_name' => 'Tester',
            'email' => 'author@example.test',
            'password' => 'password123456',
        ]);

        $editor = User::query()->create([
            'given_name' => 'Editor',
            'family_name' => 'Tester',
            'email' => 'editor@example.test',
            'password' => 'password123456',
        ]);

        $editorRole = Role::withoutGlobalScopes()
            ->where('name', UserRole::ScheduledConferenceEditor->value)
            ->where('conference_id', $conference->getKey())
            ->where('scheduled_conference_id', $scheduledConference->getKey())
            ->firstOrFail();

        Permission::query()->firstOrCreate([
            'name' => 'Submission:sendToEditing',
            'guard_name' => 'web',
        ]);

        $editor->assignRole($editorRole);

        $submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $author->getKey(),
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'track_id' => $track->getKey(),
            ...$submissionState,
        ]);

        $submission->participants()->create([
            'user_id' => $editor->getKey(),
            'role_id' => $editorRole->getKey(),
        ]);

        return [
            'conference' => $conference,
            'scheduledConference' => $scheduledConference,
            'track' => $track,
            'author' => $author,
            'editor' => $editor,
            'submission' => $submission,
        ];
    }
}
