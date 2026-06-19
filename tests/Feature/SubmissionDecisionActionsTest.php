<?php

namespace Tests\Feature;

use App\Constants\SubmissionFileCategory;
use App\Mail\Templates\AcceptAbstractMail;
use App\Mail\Templates\RevisionRequestMail;
use App\Mail\Templates\SendForReviewMail;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Media;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\SubmissionFileType;
use App\Models\SubmissionReviewRound;
use App\Models\Track;
use App\Models\User;
use App\Notifications\AbstractAccepted;
use App\Notifications\SubmissionSentForReview;
use App\Panel\ScheduledConference\Livewire\Submissions\CallforAbstract;
use App\Panel\ScheduledConference\Livewire\Submissions\PeerReview;
use App\Panel\ScheduledConference\Livewire\Submissions\Presentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
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

        Route::getRoutes()->refreshNameLookups();
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
            ->callAction('sendForReview', data: $this->sendForReviewNotificationData([
                'papers' => [],
            ]));

        $context['submission']->refresh();

        $this->assertSame(SubmissionStage::PeerReview, $context['submission']->stage);
        $this->assertSame(SubmissionStatus::OnReview, $context['submission']->status);
    }

    public function test_send_for_review_assigns_selected_abstract_file_to_the_initial_review_round(): void
    {
        Mail::fake();

        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::CallforAbstract,
            'status' => SubmissionStatus::Queued,
        ]);

        $type = SubmissionFileType::query()->create([
            'name' => 'Abstract',
            'scheduled_conference_id' => app()->getCurrentScheduledConferenceId(),
        ]);

        $media = Media::query()->create([
            'model_type' => Submission::class,
            'model_id' => $context['submission']->getKey(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => SubmissionFileCategory::ABSTRACT_FILES,
            'name' => 'abstract',
            'file_name' => 'abstract.pdf',
            'mime_type' => 'application/pdf',
            'disk' => 'private-files',
            'conversions_disk' => null,
            'size' => 123,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => 1,
        ]);

        $abstractFile = SubmissionFile::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'submission_file_type_id' => $type->getKey(),
            'media_id' => $media->getKey(),
            'user_id' => $context['author']->getKey(),
            'category' => SubmissionFileCategory::ABSTRACT_FILES,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(CallforAbstract::class, ['submission' => $context['submission']])
            ->callAction('sendForReview', data: $this->sendForReviewNotificationData([
                'papers' => [$abstractFile->getKey()],
            ]));

        $round = SubmissionReviewRound::query()
            ->where('submission_id', $context['submission']->getKey())
            ->where('round_number', 1)
            ->firstOrFail();

        $this->assertNull($round->name);

        $this->assertDatabaseHas('submission_files', [
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $round->getKey(),
            'category' => SubmissionFileCategory::REVIEW_FILES,
        ]);
    }

    public function test_send_for_review_can_name_the_initial_review_round(): void
    {
        Mail::fake();

        $this->assertSame('Example: Full Paper', __('general.review_round_name_placeholder'));

        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::CallforAbstract,
            'status' => SubmissionStatus::Queued,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(CallforAbstract::class, ['submission' => $context['submission']])
            ->callAction('sendForReview', data: $this->sendForReviewNotificationData([
                'review_round_name' => 'Abstract Screening',
                'papers' => [],
            ]));

        $this->assertDatabaseHas('submission_review_rounds', [
            'submission_id' => $context['submission']->getKey(),
            'round_number' => 1,
            'name' => 'Abstract Screening',
        ]);
    }

    public function test_send_for_review_creates_round_for_legacy_on_review_submission_without_round(): void
    {
        Mail::fake();

        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnReview,
        ]);

        $type = SubmissionFileType::query()->create([
            'name' => 'Abstract',
            'scheduled_conference_id' => app()->getCurrentScheduledConferenceId(),
        ]);

        $media = Media::query()->create([
            'model_type' => Submission::class,
            'model_id' => $context['submission']->getKey(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => SubmissionFileCategory::ABSTRACT_FILES,
            'name' => 'legacy-abstract',
            'file_name' => 'legacy-abstract.pdf',
            'mime_type' => 'application/pdf',
            'disk' => 'private-files',
            'conversions_disk' => null,
            'size' => 123,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => 1,
        ]);

        $abstractFile = SubmissionFile::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'submission_file_type_id' => $type->getKey(),
            'media_id' => $media->getKey(),
            'user_id' => $context['author']->getKey(),
            'category' => SubmissionFileCategory::ABSTRACT_FILES,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(CallforAbstract::class, ['submission' => $context['submission']])
            ->callAction('sendForReview', data: $this->sendForReviewNotificationData([
                'papers' => [$abstractFile->getKey()],
            ]));

        $round = SubmissionReviewRound::query()
            ->where('submission_id', $context['submission']->getKey())
            ->where('round_number', 1)
            ->firstOrFail();

        $this->assertDatabaseHas('submission_files', [
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $round->getKey(),
            'category' => SubmissionFileCategory::REVIEW_FILES,
        ]);
    }

    public function test_call_for_abstract_decision_clears_skipped_review_when_sending_a_skipped_submission_back_to_review(): void
    {
        Mail::fake();

        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::Presentation,
            'status' => SubmissionStatus::OnPresentation,
            'skipped_review' => true,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(CallforAbstract::class, ['submission' => $context['submission']])
            ->callAction('sendForReview', data: $this->sendForReviewNotificationData([
                'papers' => [],
            ]));

        $context['submission']->refresh();

        $this->assertSame(SubmissionStage::PeerReview, $context['submission']->stage);
        $this->assertSame(SubmissionStatus::OnReview, $context['submission']->status);
        $this->assertFalse($context['submission']->skipped_review);

        Livewire::test(PeerReview::class, ['submission' => $context['submission']])
            ->assertDontSee(__('general.review_skipped'));
    }

    public function test_call_for_abstract_decision_clears_stale_skipped_review_when_resending_to_review(): void
    {
        Mail::fake();

        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnReview,
            'skipped_review' => true,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(CallforAbstract::class, ['submission' => $context['submission']])
            ->callAction('sendForReview', data: $this->sendForReviewNotificationData([
                'papers' => [],
            ]));

        $context['submission']->refresh();

        $this->assertSame(SubmissionStage::PeerReview, $context['submission']->stage);
        $this->assertSame(SubmissionStatus::OnReview, $context['submission']->status);
        $this->assertFalse($context['submission']->skipped_review);

        Livewire::test(PeerReview::class, ['submission' => $context['submission']])
            ->assertDontSee(__('general.review_skipped'));
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

    public function test_peer_review_request_revision_defaults_to_notifying_author_when_checkbox_is_omitted(): void
    {
        Mail::fake();

        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::Presentation,
            'status' => SubmissionStatus::OnPresentation,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(PeerReview::class, ['submission' => $context['submission']])
            ->callAction('requestRevisionAction', data: [
                'email' => $context['author']->email,
                'subject' => 'Need revision',
                'message' => 'Please revise.',
            ]);

        Mail::assertQueued(RevisionRequestMail::class);
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

    public function test_submission_state_uses_send_for_review_naming(): void
    {
        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::CallforAbstract,
            'status' => SubmissionStatus::Queued,
        ]);

        $this->actingAs($context['editor']);

        $state = $context['submission']->state();

        $this->assertTrue(method_exists($state, 'sendForReview'));
        $this->assertFalse(method_exists($state, 'acceptAbstract'));

        $state->sendForReview();
        $context['submission']->refresh();

        $this->assertSame(SubmissionStage::PeerReview, $context['submission']->stage);
        $this->assertSame(SubmissionStatus::OnReview, $context['submission']->status);
    }

    public function test_send_for_review_notification_and_mail_use_send_for_review_wording(): void
    {
        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::CallforAbstract,
            'status' => SubmissionStatus::Queued,
        ]);

        $this->actingAs($context['editor']);

        $notification = new SubmissionSentForReview($context['submission']);
        $databaseMessage = $notification->toDatabase($context['author']);

        $this->assertSame(__('general.submission_sent_for_review'), data_get($databaseMessage->toArray(), 'title'));
        $this->assertInstanceOf(SendForReviewMail::class, $notification->toMail($context['author']));
        $this->assertSame('Submission Sent for Review', SendForReviewMail::getDefaultDescription());
        $this->assertStringNotContainsString('accepted', strtolower(SendForReviewMail::getDefaultDescription()));
        $this->assertStringNotContainsString('accept abstract', strtolower(SendForReviewMail::getDefaultHtmlTemplate()));
    }

    public function test_legacy_send_for_review_classes_remain_compatible(): void
    {
        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::CallforAbstract,
            'status' => SubmissionStatus::Queued,
        ]);

        $this->actingAs($context['editor']);

        $this->assertInstanceOf(SendForReviewMail::class, new AcceptAbstractMail($context['submission']));
        $this->assertInstanceOf(
            SubmissionSentForReview::class,
            new AbstractAccepted($context['submission'])
        );
    }

    public function test_send_for_review_class_reference_migration_updates_legacy_records(): void
    {
        $context = $this->makeEditorSubmissionContext([
            'stage' => SubmissionStage::CallforAbstract,
            'status' => SubmissionStatus::Queued,
        ]);

        $mailTemplateId = DB::table('mail_templates')->insertGetId([
            'conference_id' => $context['conference']->getKey(),
            'mailable' => AcceptAbstractMail::class,
            'description' => 'Legacy template',
            'subject' => 'Legacy subject',
            'html_template' => '<p>Legacy template</p>',
            'text_template' => 'Legacy template',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $notificationId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => AbstractAccepted::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $context['author']->getKey(),
            'data' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_06_18_000002_rename_accept_abstract_to_send_for_review.php');
        $migration->up();

        $this->assertDatabaseHas('mail_templates', [
            'id' => $mailTemplateId,
            'mailable' => SendForReviewMail::class,
        ]);
        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'type' => SubmissionSentForReview::class,
        ]);
    }

    protected function sendForReviewNotificationData(array $overrides = []): array
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
