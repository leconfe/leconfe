<?php

namespace Tests\Feature;

use App\Actions\SubmissionFiles\UploadSubmissionFileAction;
use App\Actions\Submissions\CloneSubmissionFilesToReviewRoundAction;
use App\Actions\Submissions\NotifySubmissionRevisionRequestAction;
use App\Actions\Submissions\StartSubmissionReviewRoundAction;
use App\Actions\Submissions\SubmissionUpdateAction;
use App\Constants\ReviewerStatus;
use App\Constants\SubmissionFileCategory;
use App\Mail\Templates\NewPaperUploadedMail;
use App\Mail\Templates\NewReviewFileUploadedMail;
use App\Mail\Templates\NewRevisionUploadedMail;
use App\Mail\Templates\ReviewRoundStartedMail;
use App\Models\Conference;
use App\Models\DefaultMailTemplate;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Media;
use App\Models\Permission;
use App\Models\Review;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\SubmissionFileType;
use App\Models\SubmissionReviewRound;
use App\Models\Track;
use App\Models\User;
use App\Notifications\SubmissionFileUploaded;
use App\Notifications\SubmissionReviewRoundStarted;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\ReviewFiles;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ReviewerList;
use App\Panel\ScheduledConference\Livewire\Submissions\PeerReview;
use App\Panel\ScheduledConference\Pages\ReviewResult;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ReviewerInvitationPage;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ReviewSubmissionPage;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ViewSubmission;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SubmissionReviewRoundWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test/submissions/{record}/review', fn () => null)
            ->name('filament.conference.resources.submissions.review');

        Route::get('/test/submissions/{record}', fn () => null)
            ->name('filament.conference.resources.submissions.view');
    }

    public function test_it_starts_a_new_review_round_and_closes_the_previous_open_round(): void
    {
        $context = $this->makeSubmissionContext();

        $openRound = SubmissionReviewRound::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'round_number' => 1,
            'status' => SubmissionReviewRound::STATUS_OPEN,
            'opened_at' => now()->subDay(),
        ]);

        $pendingReview = Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $openRound->getKey(),
            'user_id' => $context['reviewerA']->getKey(),
            'status' => ReviewerStatus::PENDING,
        ]);

        $acceptedReview = Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $openRound->getKey(),
            'user_id' => $context['reviewerB']->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
        ]);

        $completedReview = Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $openRound->getKey(),
            'user_id' => $context['reviewerC']->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'recommendation' => 'Accept',
            'date_completed' => now()->subHours(2),
        ]);

        $newRound = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [99999],
            $context['editor'],
        );

        $openRound->refresh();
        $pendingReview->refresh();
        $acceptedReview->refresh();
        $completedReview->refresh();

        $this->assertSame(SubmissionReviewRound::STATUS_CLOSED, $openRound->status);
        $this->assertNotNull($openRound->closed_at);

        $this->assertSame(ReviewerStatus::CANCELED, $pendingReview->status);
        $this->assertSame(ReviewerStatus::CANCELED, $acceptedReview->status);
        $this->assertSame(ReviewerStatus::ACCEPTED, $completedReview->status);

        $this->assertSame(SubmissionReviewRound::STATUS_OPEN, $newRound->status);
        $this->assertSame(2, $newRound->round_number);
        $this->assertSame($context['editor']->getKey(), $newRound->triggered_by);
        $this->assertSame([], $newRound->default_file_ids ?? []);
    }

    public function test_request_revision_notification_does_not_change_submission_state_or_create_round(): void
    {
        $context = $this->makeSubmissionContext();

        $originalState = [
            'stage' => $context['submission']->stage,
            'status' => $context['submission']->status,
            'revision_required' => $context['submission']->revision_required,
        ];

        Mail::fake();

        NotifySubmissionRevisionRequestAction::run(
            $context['submission'],
            'Need revision',
            '<p>Please revise.</p>',
            false,
            $context['editor'],
        );

        Mail::assertNothingSent();

        $context['submission']->refresh();

        $this->assertTrue($context['submission']->stage->is($originalState['stage']));
        $this->assertTrue($context['submission']->status->is($originalState['status']));
        $this->assertSame($originalState['revision_required'], $context['submission']->revision_required);
        $this->assertDatabaseCount('submission_review_rounds', 0);
    }

    public function test_it_creates_initial_round_when_submission_moves_to_on_review(): void
    {
        $context = $this->makeSubmissionContext([
            'stage' => SubmissionStage::CallforAbstract,
            'status' => SubmissionStatus::Queued,
        ]);

        $this->actingAs($context['editor']);

        SubmissionUpdateAction::run([
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnReview,
        ], $context['submission']);

        $context['submission']->refresh();

        $this->assertDatabaseHas('submission_review_rounds', [
            'submission_id' => $context['submission']->getKey(),
            'round_number' => 1,
            'status' => SubmissionReviewRound::STATUS_OPEN,
        ]);
    }

    public function test_initial_round_inherits_existing_unscoped_review_files(): void
    {
        $context = $this->makeSubmissionContext([
            'stage' => SubmissionStage::CallforAbstract,
            'status' => SubmissionStatus::Queued,
        ]);

        $media = Media::query()->create([
            'model_type' => Submission::class,
            'model_id' => $context['submission']->getKey(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => SubmissionFileCategory::REVIEW_FILES,
            'name' => 'legacy-paper',
            'file_name' => 'legacy-paper.pdf',
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

        $type = SubmissionFileType::query()->create([
            'name' => 'Paper',
            'scheduled_conference_id' => app()->getCurrentScheduledConferenceId(),
        ]);

        $legacyFile = SubmissionFile::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'submission_file_type_id' => $type->getKey(),
            'media_id' => $media->getKey(),
            'user_id' => $context['submission']->user_id,
            'category' => SubmissionFileCategory::REVIEW_FILES,
        ]);

        $this->actingAs($context['editor']);

        SubmissionUpdateAction::run([
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnReview,
        ], $context['submission']);

        $round = SubmissionReviewRound::query()
            ->where('submission_id', $context['submission']->getKey())
            ->where('round_number', 1)
            ->firstOrFail();

        $legacyFile->refresh();
        $round->refresh();

        $this->assertSame($round->getKey(), $legacyFile->review_round_id);
        $this->assertContains($legacyFile->getKey(), $round->default_file_ids);
    }

    public function test_it_creates_initial_round_when_submission_moves_to_on_payment_in_peer_review_stage(): void
    {
        $context = $this->makeSubmissionContext([
            'stage' => SubmissionStage::CallforAbstract,
            'status' => SubmissionStatus::Queued,
        ]);

        $this->actingAs($context['editor']);

        SubmissionUpdateAction::run([
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnPayment,
        ], $context['submission']);

        $context['submission']->refresh();

        $this->assertDatabaseHas('submission_review_rounds', [
            'submission_id' => $context['submission']->getKey(),
            'round_number' => 1,
            'status' => SubmissionReviewRound::STATUS_OPEN,
        ]);
    }

    public function test_editor_can_start_the_next_review_round_from_workflow_action(): void
    {
        $context = $this->makeSubmissionContext();

        $editorRole = Role::withoutGlobalScopes()
            ->where('name', UserRole::ScheduledConferenceEditor->value)
            ->where('conference_id', app()->getCurrentConferenceId())
            ->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId())
            ->firstOrFail();

        $context['editor']->assignRole($editorRole);
        $context['submission']->participants()->create([
            'user_id' => $context['editor']->getKey(),
            'role_id' => $editorRole->getKey(),
        ]);

        StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $this->actingAs($context['editor']);

        Livewire::test(PeerReview::class, ['submission' => $context['submission']])
            ->assertSee(__('general.start_next_review_round'))
            ->assertDontSee('New Review Round')
            ->assertDontSeeText('Open')
            ->mountAction('startNextReviewRoundAction')
            ->assertSee(__('general.start_next_review_round_modal_description'))
            ->setActionData([
                'name' => 'Abstract Screening',
                'default_file_ids' => [],
            ])
            ->callMountedAction()
            ->assertSee('Abstract Screening')
            ->assertDontSee('Round 2 - Abstract Screening')
            ->assertDontSeeText('Closed')
            ->assertDontSeeText('Open');

        $this->assertDatabaseHas('submission_review_rounds', [
            'submission_id' => $context['submission']->getKey(),
            'round_number' => 2,
            'name' => 'Abstract Screening',
        ]);
    }

    public function test_start_next_review_round_modal_offers_author_notification_toggle(): void
    {
        $context = $this->makeSubmissionContext();
        $this->assignEditorRole($context['editor'], $context['submission']);

        StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $this->actingAs($context['editor']);

        Livewire::test(PeerReview::class, ['submission' => $context['submission']])
            ->mountAction('startNextReviewRoundAction')
            ->assertSee(__('general.notification'))
            ->assertSee(__('general.dont_send_notification_to_author'));
    }

    public function test_start_next_review_round_notifies_author_by_default(): void
    {
        $context = $this->makeSubmissionContext();
        $this->assignEditorRole($context['editor'], $context['submission']);

        StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        Notification::fake();

        $this->actingAs($context['editor']);

        Livewire::test(PeerReview::class, ['submission' => $context['submission']])
            ->mountAction('startNextReviewRoundAction')
            ->setActionData([
                'name' => 'Full Paper Review',
                'default_file_ids' => [],
                'subject' => 'New review round',
                'message' => 'Your submission has been sent for another review round.',
            ])
            ->callMountedAction();

        Notification::assertSentTo(
            $context['author'],
            SubmissionReviewRoundStarted::class,
            function (SubmissionReviewRoundStarted $notification) use ($context): bool {
                $databaseMessage = $notification->toDatabase($context['author']);
                $mail = $notification->toMail($context['author']);
                $mailViewData = $mail->buildViewData();

                return $notification->submission->is($context['submission'])
                    && $notification->reviewRound->round_number === 2
                    && $notification->via($context['author']) === ['database', 'mail']
                    && $mail instanceof ReviewRoundStartedMail
                    && data_get($mailViewData, 'Review Round Name') === 'Full Paper Review'
                    && data_get($databaseMessage->toArray(), 'title') === __('general.sent_for_a_new_round_of_reviews');
            }
        );
    }

    public function test_start_next_review_round_can_skip_author_notification(): void
    {
        $context = $this->makeSubmissionContext();
        $this->assignEditorRole($context['editor'], $context['submission']);

        StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        Notification::fake();

        $this->actingAs($context['editor']);

        Livewire::test(PeerReview::class, ['submission' => $context['submission']])
            ->mountAction('startNextReviewRoundAction')
            ->setActionData([
                'name' => 'Full Paper Review',
                'default_file_ids' => [],
                'subject' => 'New review round',
                'message' => 'Your submission has been sent for another review round.',
                'do-not-notify-author' => true,
            ])
            ->callMountedAction();

        Notification::assertNotSentTo($context['author'], SubmissionReviewRoundStarted::class);
    }

    public function test_historical_review_round_hides_peer_review_workflow_actions(): void
    {
        $context = $this->makeSubmissionContext();

        $editorRole = Role::withoutGlobalScopes()
            ->where('name', UserRole::ScheduledConferenceEditor->value)
            ->where('conference_id', app()->getCurrentConferenceId())
            ->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId())
            ->firstOrFail();

        $context['editor']->assignRole($editorRole);
        $context['submission']->participants()->create([
            'user_id' => $context['editor']->getKey(),
            'role_id' => $editorRole->getKey(),
        ]);

        $roundOne = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $roundTwo = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $this->actingAs($context['editor']);

        Livewire::test(PeerReview::class, ['submission' => $context['submission']])
            ->assertActionVisible('startNextReviewRoundAction')
            ->assertActionVisible('requestRevisionAction')
            ->assertActionVisible('acceptSubmissionAction')
            ->assertActionVisible('declineSubmissionAction')
            ->call('selectRound', $roundOne->getKey())
            ->assertSee('Round 1')
            ->assertSee('Round 2')
            ->assertSee(__('general.sent_for_a_new_round_of_reviews'))
            ->assertActionHidden('startNextReviewRoundAction')
            ->assertActionHidden('requestRevisionAction')
            ->assertActionHidden('acceptSubmissionAction')
            ->assertActionHidden('declineSubmissionAction')
            ->assertDontSeeText(__('general.start_next_review_round'))
            ->assertDontSeeText(__('general.request_revision'))
            ->assertDontSeeText(__('general.accept_submission'))
            ->assertDontSeeText(__('general.decline_submission'))
            ->call('selectRound', $roundTwo->getKey())
            ->assertActionVisible('startNextReviewRoundAction')
            ->assertActionVisible('requestRevisionAction')
            ->assertActionVisible('acceptSubmissionAction')
            ->assertActionVisible('declineSubmissionAction');
    }

    public function test_peer_review_round_switch_has_section_loading_feedback(): void
    {
        $context = $this->makeSubmissionContext();

        $editorRole = Role::withoutGlobalScopes()
            ->where('name', UserRole::ScheduledConferenceEditor->value)
            ->where('conference_id', app()->getCurrentConferenceId())
            ->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId())
            ->firstOrFail();

        $context['editor']->assignRole($editorRole);
        $context['submission']->participants()->create([
            'user_id' => $context['editor']->getKey(),
            'role_id' => $editorRole->getKey(),
        ]);

        StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $this->actingAs($context['editor']);

        $html = Livewire::test(PeerReview::class, ['submission' => $context['submission']])
            ->assertSee('Round 1')
            ->assertSee('Round 2')
            ->html();

        $this->assertStringContainsString('peer-review-round-switch-loading', $html);
        $this->assertStringContainsString('wire:loading.delay.flex', $html);
        $this->assertStringContainsString('wire:target="selectRound"', $html);
        $this->assertStringContainsString(__('general.loading'), $html);

        $this->assertStringContainsString('peer-review-round-switch-content', $html);
        $this->assertStringContainsString('wire:loading.class.delay="opacity-50 pointer-events-none"', $html);

        $this->assertMatchesRegularExpression(
            '/<button[^>]*wire:click="selectRound\(\d+\)"[^>]*wire:loading\.attr="disabled"[^>]*wire:target="selectRound"/s',
            $html,
        );
    }

    public function test_editor_can_rename_a_review_round_from_the_tab_icon(): void
    {
        $context = $this->makeSubmissionContext();

        $editorRole = Role::withoutGlobalScopes()
            ->where('name', UserRole::ScheduledConferenceEditor->value)
            ->where('conference_id', app()->getCurrentConferenceId())
            ->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId())
            ->firstOrFail();

        $context['editor']->assignRole($editorRole);
        $context['submission']->participants()->create([
            'user_id' => $context['editor']->getKey(),
            'role_id' => $editorRole->getKey(),
        ]);

        $roundOne = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $roundTwo = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $this->actingAs($context['editor']);

        $roundTabHtml = function (string $html, string $label): string {
            preg_match_all('/<div[^>]*class="[^"]*peer-review-round-tab[^"]*"[^>]*>.*?<\/div>/s', $html, $matches);

            foreach ($matches[0] as $tabHtml) {
                if (str_contains(html_entity_decode($tabHtml), $label)) {
                    return $tabHtml;
                }
            }

            $this->fail("Unable to find review round tab for [{$label}].");
        };

        $roundTabEditButtonHtml = function (string $tabHtml): string {
            preg_match('/<button[^>]*class="[^"]*peer-review-round-tab-edit[^"]*"[^>]*>.*?<\/button>/s', $tabHtml, $matches);

            return $matches[0] ?? $this->fail('Unable to find review round tab edit button.');
        };

        $component = Livewire::test(PeerReview::class, ['submission' => $context['submission']])
            ->assertSee('Round 1')
            ->assertSee('Round 2')
            ->assertSee(__('general.rename_review_round'));

        $this->assertStringNotContainsString(
            'peer-review-round-tab-edit',
            $roundTabHtml($component->html(), 'Round 1'),
        );

        $this->assertStringContainsString(
            'peer-review-round-tab-edit',
            $roundTabHtml($component->html(), 'Round 2'),
        );

        $this->assertStringContainsString(
            'fi-tabs-item-icon',
            $roundTabEditButtonHtml($roundTabHtml($component->html(), 'Round 2')),
        );

        $this->assertStringNotContainsString(
            'text-primary-600',
            $roundTabEditButtonHtml($roundTabHtml($component->html(), 'Round 2')),
        );

        $this->assertStringContainsString(
            'text-primary-600',
            $roundTabHtml($component->html(), 'Round 2'),
        );

        $this->assertStringNotContainsString(
            'hover:text-primary-700',
            $roundTabHtml($component->html(), 'Round 2'),
        );

        $this->assertStringNotContainsString(
            'dark:hover:text-primary-300',
            $roundTabHtml($component->html(), 'Round 2'),
        );

        $component
            ->call('selectRound', $roundOne->getKey())
            ->assertSet('selectedRoundId', $roundOne->getKey());

        $this->assertStringContainsString(
            'peer-review-round-tab-edit',
            $roundTabHtml($component->html(), 'Round 1'),
        );

        $this->assertStringNotContainsString(
            'peer-review-round-tab-edit',
            $roundTabHtml($component->html(), 'Round 2'),
        );

        $component
            ->mountAction('renameReviewRoundAction', ['round' => $roundTwo->getKey()])
            ->setActionData([
                'name' => 'Should Not Rename',
            ])
            ->callMountedAction();

        $this->assertDatabaseMissing('submission_review_rounds', [
            'id' => $roundTwo->getKey(),
            'name' => 'Should Not Rename',
        ]);

        $this->assertDatabaseHas('submission_review_rounds', [
            'id' => $roundTwo->getKey(),
            'name' => null,
        ]);

        $component
            ->assertSee('Round 1')
            ->assertSee('Round 2')
            ->assertSet('selectedRoundId', $roundOne->getKey());

        $this->assertStringContainsString(
            'peer-review-round-tab-edit',
            $roundTabHtml($component->html(), 'Round 1'),
        );

        $component
            ->mountAction('renameReviewRoundAction', ['round' => $roundOne->getKey()])
            ->assertSee(__('general.rename_review_round'));

        $this->assertSame(MaxWidth::Medium, $component->instance()->getMountedAction()?->getModalWidth());

        $component
            ->setActionData([
                'name' => 'Full Paper',
            ])
            ->callMountedAction()
            ->assertSee('Full Paper')
            ->assertDontSee('Round 1');

        $this->assertDatabaseHas('submission_review_rounds', [
            'id' => $roundOne->getKey(),
            'name' => 'Full Paper',
        ]);
    }

    public function test_review_files_migration_converts_legacy_paper_files_data(): void
    {
        $context = $this->makeSubmissionContext();

        $type = SubmissionFileType::query()->create([
            'name' => 'Abstract',
            'scheduled_conference_id' => app()->getCurrentScheduledConferenceId(),
        ]);

        $mediaId = DB::table('media')->insertGetId([
            'model_type' => Submission::class,
            'model_id' => $context['submission']->getKey(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'paper-files',
            'name' => 'abstract',
            'file_name' => 'abstract.pdf',
            'mime_type' => 'application/pdf',
            'disk' => 'private-files',
            'conversions_disk' => null,
            'size' => 123,
            'manipulations' => json_encode([]),
            'custom_properties' => json_encode([]),
            'generated_conversions' => json_encode([]),
            'responsive_images' => json_encode([]),
            'order_column' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fileId = DB::table('submission_files')->insertGetId([
            'submission_id' => $context['submission']->getKey(),
            'media_id' => $mediaId,
            'submission_file_type_id' => $type->getKey(),
            'user_id' => $context['submission']->user_id,
            'category' => 'paper-files',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_06_18_000001_rename_paper_files_to_review_files.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('submission_review_rounds', 'name'));
        $this->assertDatabaseHas('submission_files', [
            'id' => $fileId,
            'category' => SubmissionFileCategory::REVIEW_FILES,
            'submission_file_type_id' => $type->getKey(),
        ]);
        $this->assertDatabaseHas('media', [
            'id' => $mediaId,
            'collection_name' => SubmissionFileCategory::REVIEW_FILES,
        ]);
    }

    public function test_review_files_are_assigned_to_the_active_review_round(): void
    {
        $context = $this->makeSubmissionContext();
        $round = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $this->actingAs($context['editor']);

        $media = Media::query()->create([
            'model_type' => Submission::class,
            'model_id' => $context['submission']->getKey(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => SubmissionFileCategory::REVIEW_FILES,
            'name' => 'paper',
            'file_name' => 'paper.pdf',
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

        $type = SubmissionFileType::query()->create([
            'name' => 'Paper',
            'scheduled_conference_id' => app()->getCurrentScheduledConferenceId(),
        ]);

        UploadSubmissionFileAction::run(
            $context['submission'],
            $media,
            SubmissionFileCategory::REVIEW_FILES,
            $type,
            $round->getKey(),
        );

        $this->assertDatabaseHas('submission_files', [
            'submission_id' => $context['submission']->getKey(),
            'media_id' => $media->getKey(),
            'review_round_id' => $round->getKey(),
            'category' => SubmissionFileCategory::REVIEW_FILES,
        ]);
    }

    public function test_review_files_upload_modal_does_not_show_editor_notification_notice(): void
    {
        $context = $this->makeSubmissionContext();
        $this->actingAs($context['editor']);
        $this->assignEditorRole($context['editor'], $context['submission']);

        StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        Livewire::test(ReviewFiles::class, ['submission' => $context['submission']])
            ->mountTableAction('upload')
            ->assertDontSee('After uploading review files, the system will send a notification to the editor.');
    }

    public function test_review_file_upload_notifies_editors_only_when_uploaded_by_author(): void
    {
        $context = $this->makeSubmissionContext();
        $round = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $this->assignEditorRole($context['editor'], $context['submission']);

        $authorRole = $this->createAuthorRole();
        $context['submission']->participants()->create([
            'user_id' => $context['author']->getKey(),
            'role_id' => $authorRole->getKey(),
        ]);

        $type = SubmissionFileType::query()->create([
            'name' => 'Paper',
            'scheduled_conference_id' => app()->getCurrentScheduledConferenceId(),
        ]);

        Notification::fake();

        SubmissionFile::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'media_id' => $this->createSubmissionMedia($context['submission'], SubmissionFileCategory::REVIEW_FILES, 'review-file')->getKey(),
            'review_round_id' => $round->getKey(),
            'submission_file_type_id' => $type->getKey(),
            'user_id' => $context['editor']->getKey(),
            'category' => SubmissionFileCategory::REVIEW_FILES,
        ]);

        Notification::assertNotSentTo(
            $context['editor'],
            SubmissionFileUploaded::class,
            fn (SubmissionFileUploaded $notification): bool => $notification->submissionFile->category === SubmissionFileCategory::REVIEW_FILES
        );

        SubmissionFile::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'media_id' => $this->createSubmissionMedia($context['submission'], SubmissionFileCategory::REVIEW_FILES, 'author-review-file')->getKey(),
            'review_round_id' => $round->getKey(),
            'submission_file_type_id' => $type->getKey(),
            'user_id' => $context['author']->getKey(),
            'category' => SubmissionFileCategory::REVIEW_FILES,
        ]);

        Notification::assertSentTo(
            $context['editor'],
            SubmissionFileUploaded::class,
            fn (SubmissionFileUploaded $notification): bool => $notification->submissionFile->category === SubmissionFileCategory::REVIEW_FILES
                && $notification->via($context['editor']) === ['mail', 'database']
        );

        SubmissionFile::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'media_id' => $this->createSubmissionMedia($context['submission'], SubmissionFileCategory::REVISION_FILES, 'revision-file')->getKey(),
            'review_round_id' => $round->getKey(),
            'submission_file_type_id' => $type->getKey(),
            'user_id' => $context['author']->getKey(),
            'category' => SubmissionFileCategory::REVISION_FILES,
        ]);

        Notification::assertSentTo(
            $context['editor'],
            SubmissionFileUploaded::class,
            fn (SubmissionFileUploaded $notification): bool => $notification->submissionFile->category === SubmissionFileCategory::REVISION_FILES
        );
    }

    public function test_review_file_upload_email_template_uses_review_file_context(): void
    {
        $this->makeSubmissionContext();

        $mailTemplates = collect((new DefaultMailTemplate)->getDefaultData(app()->getCurrentConference()));
        $mailables = $mailTemplates->pluck('mailable');

        $this->assertTrue($mailables->contains(NewReviewFileUploadedMail::class));
        $this->assertFalse($mailables->contains(NewPaperUploadedMail::class));
        $this->assertTrue($mailables->contains(NewRevisionUploadedMail::class));

        $reviewFileTemplate = $mailTemplates->firstWhere('mailable', NewReviewFileUploadedMail::class);
        $revisionFileTemplate = $mailTemplates->firstWhere('mailable', NewRevisionUploadedMail::class);

        $this->assertSame('New review file uploaded for {{ Submission Title }}', $reviewFileTemplate['subject']);
        $this->assertStringContainsString('A review file has been uploaded', $reviewFileTemplate['html_template']);
        $this->assertStringContainsString('{{ Uploaded By }}', $reviewFileTemplate['html_template']);
        $this->assertStringContainsString('{{ File Name }}', $reviewFileTemplate['html_template']);
        $this->assertStringContainsString('{{ Review Round Name }}', $reviewFileTemplate['html_template']);

        $this->assertSame('New revision uploaded for {{ Submission Title }}', $revisionFileTemplate['subject']);
        $this->assertStringContainsString('A revision file has been uploaded', $revisionFileTemplate['html_template']);
        $this->assertStringContainsString('{{ Uploaded By }}', $revisionFileTemplate['html_template']);
        $this->assertStringContainsString('{{ File Name }}', $revisionFileTemplate['html_template']);
        $this->assertStringContainsString('{{ Review Round Name }}', $revisionFileTemplate['html_template']);
    }

    public function test_legacy_new_paper_uploaded_mail_template_migrates_to_review_file_upload_mail(): void
    {
        $context = $this->makeSubmissionContext();

        $mailTemplateId = DB::table('mail_templates')->insertGetId([
            'conference_id' => app()->getCurrentConferenceId(),
            'mailable' => NewPaperUploadedMail::class,
            'description' => 'Legacy review file template',
            'subject' => 'Legacy subject',
            'html_template' => '<p>Legacy template</p>',
            'text_template' => 'Legacy template',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_06_19_000001_rename_new_paper_uploaded_to_new_review_file_uploaded.php');
        $migration->up();

        $this->assertDatabaseHas('mail_templates', [
            'id' => $mailTemplateId,
            'mailable' => NewReviewFileUploadedMail::class,
        ]);
    }

    public function test_renaming_a_submission_file_updates_the_displayed_name(): void
    {
        $context = $this->makeSubmissionContext();
        $this->actingAs($context['editor']);

        $round = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $this->assignEditorRole($context['editor'], $context['submission']);

        $media = Media::query()->create([
            'model_type' => Submission::class,
            'model_id' => $context['submission']->getKey(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => SubmissionFileCategory::REVIEW_FILES,
            'name' => 'paper-round-one',
            'file_name' => 'paper-round-one.pdf',
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

        $type = SubmissionFileType::query()->create([
            'name' => 'Paper',
            'scheduled_conference_id' => app()->getCurrentScheduledConferenceId(),
        ]);

        UploadSubmissionFileAction::run(
            $context['submission'],
            $media,
            SubmissionFileCategory::REVIEW_FILES,
            $type,
            $round->getKey(),
        );

        $file = SubmissionFile::query()
            ->where('submission_id', $context['submission']->getKey())
            ->where('review_round_id', $round->getKey())
            ->firstOrFail();

        Livewire::test(ReviewFiles::class, ['submission' => $context['submission']])
            ->callTableAction('rename', $file, data: [
                'name' => 'renamed-paper',
                'type' => $type->getKey(),
            ])
            ->assertHasNoTableActionErrors();

        $media->refresh();

        $this->assertSame('renamed-paper', $media->name);
        $this->assertSame('paper-round-one.pdf', $media->file_name);
        $this->assertSame('renamed-paper.pdf', $media->original_file_name);
    }

    public function test_author_can_upload_review_files_but_cannot_edit_delete_or_select_previous_files(): void
    {
        $context = $this->makeSubmissionContext();
        $round = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $authorRole = $this->createAuthorRole();
        $context['author']->assignRole($authorRole);
        $context['submission']->participants()->create([
            'user_id' => $context['author']->getKey(),
            'role_id' => $authorRole->getKey(),
        ]);

        $media = Media::query()->create([
            'model_type' => Submission::class,
            'model_id' => $context['submission']->getKey(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => SubmissionFileCategory::REVIEW_FILES,
            'name' => 'paper-round-one',
            'file_name' => 'paper-round-one.pdf',
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

        $type = SubmissionFileType::query()->create([
            'name' => 'Paper',
            'scheduled_conference_id' => app()->getCurrentScheduledConferenceId(),
        ]);

        $this->actingAs($context['editor']);

        UploadSubmissionFileAction::run(
            $context['submission'],
            $media,
            SubmissionFileCategory::REVIEW_FILES,
            $type,
            $round->getKey(),
        );

        $file = SubmissionFile::query()
            ->where('submission_id', $context['submission']->getKey())
            ->where('review_round_id', $round->getKey())
            ->firstOrFail();

        $this->actingAs($context['author']);
        $this->assertFalse($context['author']->can('deleteFile', $context['submission']));

        Livewire::test(ReviewFiles::class, ['submission' => $context['submission']])
            ->assertTableActionVisible('upload')
            ->assertTableActionHidden('download_all')
            ->assertTableActionHidden('select-files')
            ->assertTableActionHidden('rename', $file)
            ->assertTableActionHidden('delete', $file);
    }

    public function test_selected_files_are_cloned_into_the_next_review_round(): void
    {
        $context = $this->makeSubmissionContext();
        $this->actingAs($context['editor']);

        $firstRound = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $media = Media::query()->create([
            'model_type' => Submission::class,
            'model_id' => $context['submission']->getKey(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => SubmissionFileCategory::REVIEW_FILES,
            'name' => 'paper',
            'file_name' => 'paper-round-one.pdf',
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

        $type = SubmissionFileType::query()->create([
            'name' => 'Paper',
            'scheduled_conference_id' => app()->getCurrentScheduledConferenceId(),
        ]);

        UploadSubmissionFileAction::run(
            $context['submission'],
            $media,
            SubmissionFileCategory::REVIEW_FILES,
            $type,
            $firstRound->getKey(),
        );

        $sourceFile = SubmissionFile::query()
            ->where('submission_id', $context['submission']->getKey())
            ->where('review_round_id', $firstRound->getKey())
            ->firstOrFail();

        $secondRound = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [$sourceFile->getKey()],
            $context['editor'],
        );

        $clonedFile = SubmissionFile::query()
            ->where('submission_id', $context['submission']->getKey())
            ->where('review_round_id', $secondRound->getKey())
            ->firstOrFail();

        $this->assertNotSame($sourceFile->getKey(), $clonedFile->getKey());
        $this->assertNotSame($sourceFile->media_id, $clonedFile->media_id);
        $this->assertSame($sourceFile->submission_file_type_id, $clonedFile->submission_file_type_id);
        $this->assertSame(SubmissionFileCategory::REVIEW_FILES, $clonedFile->category);
        $this->assertDatabaseHas('submission_files', [
            'id' => $clonedFile->getKey(),
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $secondRound->getKey(),
            'category' => SubmissionFileCategory::REVIEW_FILES,
        ]);
        $this->assertContains($clonedFile->getKey(), $secondRound->default_file_ids);
    }

    public function test_selected_revision_files_are_cloned_as_review_files_in_the_next_review_round(): void
    {
        $context = $this->makeSubmissionContext();
        $this->actingAs($context['editor']);

        $firstRound = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $paperMedia = Media::query()->create([
            'model_type' => Submission::class,
            'model_id' => $context['submission']->getKey(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => SubmissionFileCategory::REVIEW_FILES,
            'name' => 'paper-round-one',
            'file_name' => 'paper-round-one.pdf',
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

        $revisionMedia = Media::query()->create([
            'model_type' => Submission::class,
            'model_id' => $context['submission']->getKey(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => SubmissionFileCategory::REVISION_FILES,
            'name' => 'revision-round-one',
            'file_name' => 'revision-round-one.pdf',
            'mime_type' => 'application/pdf',
            'disk' => 'private-files',
            'conversions_disk' => null,
            'size' => 321,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => 2,
        ]);

        $type = SubmissionFileType::query()->create([
            'name' => 'Paper',
            'scheduled_conference_id' => app()->getCurrentScheduledConferenceId(),
        ]);

        UploadSubmissionFileAction::run(
            $context['submission'],
            $paperMedia,
            SubmissionFileCategory::REVIEW_FILES,
            $type,
            $firstRound->getKey(),
        );

        UploadSubmissionFileAction::run(
            $context['submission'],
            $revisionMedia,
            SubmissionFileCategory::REVISION_FILES,
            $type,
            $firstRound->getKey(),
        );

        $revisionSourceFile = SubmissionFile::query()
            ->where('submission_id', $context['submission']->getKey())
            ->where('review_round_id', $firstRound->getKey())
            ->where('category', SubmissionFileCategory::REVISION_FILES)
            ->firstOrFail();

        $secondRound = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [$revisionSourceFile->getKey()],
            $context['editor'],
        );

        $clonedFile = SubmissionFile::query()
            ->where('submission_id', $context['submission']->getKey())
            ->where('review_round_id', $secondRound->getKey())
            ->firstOrFail();

        $this->assertSame(SubmissionFileCategory::REVIEW_FILES, $clonedFile->category);
        $this->assertContains($clonedFile->getKey(), $secondRound->default_file_ids);
    }

    public function test_previous_round_files_can_be_taken_into_the_active_round(): void
    {
        $context = $this->makeSubmissionContext();
        $this->actingAs($context['editor']);

        $firstRound = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $media = Media::query()->create([
            'model_type' => Submission::class,
            'model_id' => $context['submission']->getKey(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => SubmissionFileCategory::REVIEW_FILES,
            'name' => 'paper',
            'file_name' => 'paper-round-one.pdf',
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

        $type = SubmissionFileType::query()->create([
            'name' => 'Paper',
            'scheduled_conference_id' => app()->getCurrentScheduledConferenceId(),
        ]);

        UploadSubmissionFileAction::run(
            $context['submission'],
            $media,
            SubmissionFileCategory::REVIEW_FILES,
            $type,
            $firstRound->getKey(),
        );

        $sourceFile = SubmissionFile::query()
            ->where('submission_id', $context['submission']->getKey())
            ->where('review_round_id', $firstRound->getKey())
            ->firstOrFail();

        $secondRound = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $clonedFileIds = CloneSubmissionFilesToReviewRoundAction::run(
            $context['submission'],
            $secondRound,
            [$sourceFile->getKey()]
        );

        $this->assertCount(1, $clonedFileIds);

        $clonedFile = SubmissionFile::query()
            ->where('submission_id', $context['submission']->getKey())
            ->where('review_round_id', $secondRound->getKey())
            ->firstOrFail();

        $this->assertSame($clonedFileIds[0], $clonedFile->getKey());
        $this->assertNotSame($sourceFile->getKey(), $clonedFile->getKey());
        $this->assertNotSame($sourceFile->media_id, $clonedFile->media_id);
        $this->assertContains($clonedFile->getKey(), $secondRound->default_file_ids);
    }

    public function test_previous_round_revision_files_can_be_taken_into_the_active_round_as_review_files(): void
    {
        $context = $this->makeSubmissionContext();
        $this->actingAs($context['editor']);
        $this->assignEditorRole($context['editor'], $context['submission']);

        $firstRound = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $revisionMedia = Media::query()->create([
            'model_type' => Submission::class,
            'model_id' => $context['submission']->getKey(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => SubmissionFileCategory::REVISION_FILES,
            'name' => 'revision-round-one',
            'file_name' => 'revision-round-one.pdf',
            'mime_type' => 'application/pdf',
            'disk' => 'private-files',
            'conversions_disk' => null,
            'size' => 321,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => 1,
        ]);

        $type = SubmissionFileType::query()->create([
            'name' => 'Paper',
            'scheduled_conference_id' => app()->getCurrentScheduledConferenceId(),
        ]);

        UploadSubmissionFileAction::run(
            $context['submission'],
            $revisionMedia,
            SubmissionFileCategory::REVISION_FILES,
            $type,
            $firstRound->getKey(),
        );

        $revisionFile = SubmissionFile::query()
            ->where('submission_id', $context['submission']->getKey())
            ->where('review_round_id', $firstRound->getKey())
            ->where('category', SubmissionFileCategory::REVISION_FILES)
            ->firstOrFail();

        $secondRound = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        Livewire::test(ReviewFiles::class, ['submission' => $context['submission']])
            ->callTableAction('select-files', data: [
                'file_ids' => [$revisionFile->getKey()],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('submission_files', [
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $secondRound->getKey(),
            'category' => SubmissionFileCategory::REVIEW_FILES,
        ]);
    }

    public function test_assigning_reviewer_ignores_files_outside_the_selected_round(): void
    {
        $context = $this->makeSubmissionContext();
        $this->actingAs($context['editor']);

        $editorRole = Role::withoutGlobalScopes()
            ->where('name', UserRole::ScheduledConferenceEditor->value)
            ->where('conference_id', app()->getCurrentConferenceId())
            ->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId())
            ->firstOrFail();

        $context['editor']->assignRole($editorRole);
        $context['submission']->participants()->create([
            'user_id' => $context['editor']->getKey(),
            'role_id' => $editorRole->getKey(),
        ]);

        $context['reviewerA']->assignRole($this->createReviewerRole());

        $firstRound = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $type = SubmissionFileType::query()->create([
            'name' => 'Paper',
            'scheduled_conference_id' => app()->getCurrentScheduledConferenceId(),
        ]);

        $roundOneMedia = Media::query()->create([
            'model_type' => Submission::class,
            'model_id' => $context['submission']->getKey(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => SubmissionFileCategory::REVIEW_FILES,
            'name' => 'paper-round-one',
            'file_name' => 'paper-round-one.pdf',
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

        UploadSubmissionFileAction::run(
            $context['submission'],
            $roundOneMedia,
            SubmissionFileCategory::REVIEW_FILES,
            $type,
            $firstRound->getKey(),
        );

        $roundOneFile = SubmissionFile::query()
            ->where('review_round_id', $firstRound->getKey())
            ->firstOrFail();

        $secondRound = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );
        $secondRound->update([
            'default_file_ids' => [$roundOneFile->getKey()],
        ]);

        Livewire::test(ReviewerList::class, ['record' => $context['submission']])
            ->callTableAction('add-reviewer', data: [
                'user_id' => $context['reviewerA']->getKey(),
                'subject' => 'Review request',
                'message' => 'Please review.',
                'no-invitation-notification' => true,
                'meta' => [
                    'response_due_date' => now()->addDay()->format('Y-m-d'),
                    'review_due_date' => now()->addWeek()->format('Y-m-d'),
                    'review_mode' => Review::MODE_OPEN,
                    'open_review_for_author' => false,
                ],
            ])
            ->assertHasNoTableActionErrors();

        $review = Review::query()
            ->where('review_round_id', $secondRound->getKey())
            ->where('user_id', $context['reviewerA']->getKey())
            ->firstOrFail();

        $this->assertDatabaseMissing('reviewer_assigned_files', [
            'review_id' => $review->getKey(),
            'submission_file_id' => $roundOneFile->getKey(),
        ]);
    }

    public function test_reviewer_assignment_actions_require_the_selected_latest_open_round(): void
    {
        $context = $this->makeSubmissionContext();

        $editorRole = Role::withoutGlobalScopes()
            ->where('name', UserRole::ScheduledConferenceEditor->value)
            ->where('conference_id', app()->getCurrentConferenceId())
            ->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId())
            ->firstOrFail();

        $context['editor']->assignRole($editorRole);
        $context['submission']->participants()->create([
            'user_id' => $context['editor']->getKey(),
            'role_id' => $editorRole->getKey(),
        ]);
        $context['reviewerA']->assignRole($this->createReviewerRole());

        $roundOne = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $roundTwo = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $roundOne->update([
            'status' => SubmissionReviewRound::STATUS_OPEN,
            'closed_at' => null,
        ]);

        $this->actingAs($context['editor']);

        $component = Livewire::test(ReviewerList::class, ['record' => $context['submission']->refresh()])
            ->assertTableActionVisible('add-reviewer')
            ->call('selectRound', $roundOne->getKey())
            ->assertTableActionHidden('add-reviewer');

        $this->assertFalse($component->instance()->isSelectedRoundActive());

        $component
            ->call('selectRound', $roundTwo->getKey())
            ->assertTableActionVisible('add-reviewer');

        $this->assertTrue($component->instance()->isSelectedRoundActive());
    }

    public function test_review_email_message_is_scoped_to_the_given_round(): void
    {
        $context = $this->makeSubmissionContext();

        $roundOne = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $roundTwo = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $roundOneReview = Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $roundOne->getKey(),
            'user_id' => $context['reviewerA']->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'recommendation' => 'Accept',
            'date_completed' => now()->subHour(),
        ]);
        $roundOneReview->setMeta('review_mode', Review::MODE_OPEN);
        $roundOneReview->save();

        $roundTwoReview = Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $roundTwo->getKey(),
            'user_id' => $context['reviewerB']->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'recommendation' => 'Decline',
            'date_completed' => now(),
        ]);
        $roundTwoReview->setMeta('review_mode', Review::MODE_OPEN);
        $roundTwoReview->save();

        $roundOneMessage = $context['submission']->getReviewsEmailMessage($roundOne->getKey());
        $roundTwoMessage = $context['submission']->getReviewsEmailMessage($roundTwo->getKey());

        $this->assertStringContainsString($context['reviewerA']->fullName, $roundOneMessage);
        $this->assertStringNotContainsString($context['reviewerB']->fullName, $roundOneMessage);
        $this->assertStringContainsString('Recommendation : Accept', $roundOneMessage);

        $this->assertStringContainsString($context['reviewerB']->fullName, $roundTwoMessage);
        $this->assertStringNotContainsString($context['reviewerA']->fullName, $roundTwoMessage);
        $this->assertStringContainsString('Recommendation : Decline', $roundTwoMessage);
    }

    public function test_submission_resource_summary_uses_the_latest_review_round(): void
    {
        $context = $this->makeSubmissionContext();

        $roundOne = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $roundOne->getKey(),
            'user_id' => $context['reviewerA']->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'score' => 5,
            'recommendation' => 'Accept',
            'date_completed' => now()->subHours(2),
        ]);

        $roundTwo = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $roundTwo->getKey(),
            'user_id' => $context['reviewerB']->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'score' => 2,
            'recommendation' => 'Decline',
            'date_completed' => now()->subHour(),
        ]);

        $submission = SubmissionResource::getEloquentQuery()
            ->whereKey($context['submission']->getKey())
            ->first();

        $this->assertNotNull($submission?->latestReviewRound);
        $this->assertSame($roundTwo->getKey(), $submission->latestReviewRound->getKey());
        $this->assertSame(1, $submission->latestReviewRound->latest_round_reviews_count);
        $this->assertSame(1, $submission->latestReviewRound->latest_round_completed_reviews_count);
        $this->assertSame(2.0, (float) $submission->latestReviewRound->latest_round_reviews_avg_score);
    }

    public function test_submission_list_shows_latest_review_round_badge_for_on_review_submissions(): void
    {
        $context = $this->makeSubmissionContext();

        $editorRole = Role::withoutGlobalScopes()
            ->where('name', UserRole::ScheduledConferenceEditor->value)
            ->where('conference_id', app()->getCurrentConferenceId())
            ->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId())
            ->firstOrFail();

        $context['editor']->assignRole($editorRole);

        StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
            'Abstract Review',
        );

        StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
            'Poster Review',
        );

        $presentationSubmission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $context['submission']->user_id,
            'conference_id' => $context['submission']->conference_id,
            'scheduled_conference_id' => $context['submission']->scheduled_conference_id,
            'track_id' => $context['submission']->track_id,
            'stage' => SubmissionStage::Presentation,
            'status' => SubmissionStatus::OnPresentation,
        ]);
        $presentationSubmission->setMeta('title', 'Presentation submission');

        StartSubmissionReviewRoundAction::run(
            $presentationSubmission,
            [],
            $context['editor'],
            'Camera Ready Review',
        );

        $submission = SubmissionResource::getEloquentQuery()
            ->whereKey($context['submission']->getKey())
            ->firstOrFail();

        $this->assertSame('Poster Review', SubmissionResource::getLatestReviewRoundBadgeState($submission));
        $this->assertNotSame('Abstract Review', SubmissionResource::getLatestReviewRoundBadgeState($submission));
        $this->assertNull(SubmissionResource::getLatestReviewRoundBadgeState(
            SubmissionResource::getEloquentQuery()
                ->whereKey($presentationSubmission->getKey())
                ->firstOrFail()
        ));
    }

    public function test_submission_detail_shows_latest_review_round_badge_for_on_review_submissions(): void
    {
        $context = $this->makeSubmissionContext();

        $editorRole = Role::withoutGlobalScopes()
            ->where('name', UserRole::ScheduledConferenceEditor->value)
            ->where('conference_id', app()->getCurrentConferenceId())
            ->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId())
            ->firstOrFail();

        $context['editor']->assignRole($editorRole);

        StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
            'Poster Review',
        );

        $page = app(ViewSubmission::class);
        $page->record = $context['submission']->refresh();

        $this->assertStringContainsString(
            'Poster Review',
            (string) $page->getLatestReviewRoundBadgeHtml(),
        );

        $context['submission']->update([
            'stage' => SubmissionStage::Presentation,
            'status' => SubmissionStatus::OnPresentation,
        ]);
        $page->record = $context['submission']->refresh();

        $this->assertStringNotContainsString(
            'Poster Review',
            (string) $page->getLatestReviewRoundBadgeHtml(),
        );
    }

    public function test_review_result_includes_completed_reviews_from_closed_rounds(): void
    {
        $context = $this->makeSubmissionContext();

        $round = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $round->getKey(),
            'user_id' => $context['reviewerA']->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'score' => 4,
            'recommendation' => 'Accept',
            'date_completed' => now(),
        ]);

        $round->update([
            'status' => SubmissionReviewRound::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $submission = ReviewResult::reviewResultsQuery()
            ->whereKey($context['submission']->getKey())
            ->first();

        $this->assertNotNull($submission);
        $this->assertSame(1, $submission->effective_completed_reviews_count);
        $this->assertSame(4.0, (float) $submission->effective_reviews_avg_score);
    }

    public function test_review_result_query_keeps_submission_primary_key_for_table_records(): void
    {
        $context = $this->makeSubmissionContext();

        $round = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $round->getKey(),
            'user_id' => $context['reviewerA']->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'score' => 4,
            'recommendation' => 'Accept',
            'date_completed' => now(),
        ]);

        $submission = ReviewResult::reviewResultsQuery()
            ->whereKey($context['submission']->getKey())
            ->first();

        $this->assertNotNull($submission);
        $this->assertSame($context['submission']->getKey(), $submission->getKey());
    }

    public function test_review_result_uses_each_reviewers_latest_review_across_rounds(): void
    {
        $context = $this->makeSubmissionContext();

        $roundOne = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $roundOne->getKey(),
            'user_id' => $context['reviewerA']->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'score' => 5,
            'recommendation' => 'Accept',
            'date_completed' => now()->subHours(4),
        ]);

        Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $roundOne->getKey(),
            'user_id' => $context['reviewerB']->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'score' => 5,
            'recommendation' => 'Accept',
            'date_completed' => now()->subHours(3),
        ]);

        Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $roundOne->getKey(),
            'user_id' => $context['reviewerC']->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'score' => 1,
            'recommendation' => 'Revision',
            'date_completed' => now()->subHours(2),
        ]);

        $roundTwo = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $roundTwo->getKey(),
            'user_id' => $context['reviewerC']->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'score' => 4,
            'recommendation' => 'Accept',
            'date_completed' => now()->subHour(),
        ]);

        $submission = ReviewResult::reviewResultsQuery()
            ->whereKey($context['submission']->getKey())
            ->first();

        $this->assertNotNull($submission);
        $this->assertSame(3, $submission->effective_reviews_count);
        $this->assertSame(3, $submission->effective_completed_reviews_count);
        $this->assertEqualsWithDelta(4.6667, (float) $submission->effective_reviews_avg_score, 0.001);
    }

    public function test_review_result_keeps_pending_latest_review_in_total_count(): void
    {
        $context = $this->makeSubmissionContext();

        $roundOne = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $roundOne->getKey(),
            'user_id' => $context['reviewerA']->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'score' => 5,
            'date_completed' => now()->subHours(3),
        ]);

        Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $roundOne->getKey(),
            'user_id' => $context['reviewerB']->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'score' => 3,
            'date_completed' => now()->subHours(2),
        ]);

        Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $roundOne->getKey(),
            'user_id' => $context['reviewerC']->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'score' => 1,
            'date_completed' => now()->subHour(),
        ]);

        $roundTwo = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $roundTwo->getKey(),
            'user_id' => $context['reviewerC']->getKey(),
            'status' => ReviewerStatus::PENDING,
        ]);

        $submission = ReviewResult::reviewResultsQuery()
            ->whereKey($context['submission']->getKey())
            ->first();

        $this->assertNotNull($submission);
        $this->assertSame(3, $submission->effective_reviews_count);
        $this->assertSame(2, $submission->effective_completed_reviews_count);
        $this->assertSame(4.0, (float) $submission->effective_reviews_avg_score);
    }

    public function test_stale_review_submission_page_rejects_submit_after_a_new_round_starts(): void
    {
        $context = $this->makeSubmissionContext();
        $reviewerRole = $this->createReviewerRole();
        $context['reviewerA']->assignRole($reviewerRole);

        $roundOne = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $review = Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $roundOne->getKey(),
            'user_id' => $context['reviewerA']->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'date_confirmed' => now(),
        ]);
        $review->setMeta('review_mode', Review::MODE_OPEN);
        $review->save();

        $this->actingAs($context['reviewerA']);

        $page = new class extends ReviewSubmissionPage
        {
            public function probeResolveCurrentReview(): ?Review
            {
                return $this->resolveCurrentReview();
            }
        };

        $page->record = $context['submission'];
        $page->review = $review;

        $this->assertNotNull($page->probeResolveCurrentReview());

        StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $this->assertNull($page->probeResolveCurrentReview());

        $review->refresh();

        $this->assertSame(ReviewerStatus::CANCELED, $review->status);
        $this->assertNull($review->date_completed);
        $this->assertNull($review->recommendation);
    }

    public function test_stale_reviewer_invitation_page_rejects_accept_after_a_new_round_starts(): void
    {
        $context = $this->makeSubmissionContext();
        $reviewerRole = $this->createReviewerRole();
        $context['reviewerA']->assignRole($reviewerRole);

        $roundOne = StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $review = Review::query()->create([
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $roundOne->getKey(),
            'user_id' => $context['reviewerA']->getKey(),
            'status' => ReviewerStatus::PENDING,
        ]);
        $review->setMeta('review_mode', Review::MODE_OPEN);
        $review->save();

        $this->actingAs($context['reviewerA']);

        $page = new class extends ReviewerInvitationPage
        {
            public function probeResolveCurrentReview(): ?Review
            {
                return $this->resolveCurrentReview();
            }
        };

        $page->record = $context['submission'];
        $page->review = $review;

        $this->assertNotNull($page->probeResolveCurrentReview());

        StartSubmissionReviewRoundAction::run(
            $context['submission'],
            [],
            $context['editor'],
        );

        $this->assertNull($page->probeResolveCurrentReview());

        $review->refresh();

        $this->assertSame(ReviewerStatus::CANCELED, $review->status);
        $this->assertNull($review->date_confirmed);
    }

    protected function makeSubmissionContext(array $overrides = []): array
    {
        $conference = Conference::query()->create([
            'name' => 'Conference '.uniqid(),
            'path' => 'conf-'.uniqid(),
        ]);

        $scheduledConference = ScheduledConference::withoutGlobalScopes()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'SC '.uniqid(),
            'path' => 'sc-'.uniqid(),
            'date_start' => now()->toDateString(),
            'date_end' => now()->addDays(2)->toDateString(),
        ]);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());

        $track = Track::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'title' => 'Track '.uniqid(),
            'abbreviation' => 'TRK',
            'is_active' => true,
        ]);

        $author = User::query()->create([
            'given_name' => 'Author',
            'family_name' => 'Tester',
            'email' => 'author-'.uniqid().'@example.test',
            'password' => 'password123456',
        ]);

        $editor = User::query()->create([
            'given_name' => 'Editor',
            'family_name' => 'Tester',
            'email' => 'editor-'.uniqid().'@example.test',
            'password' => 'password123456',
        ]);

        $submission = Submission::withoutGlobalScopes()->forceCreate(array_merge([
            'user_id' => $author->getKey(),
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'track_id' => $track->getKey(),
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnReview,
            'revision_required' => false,
        ], $overrides));

        $submission->setMeta('title', 'Submission '.uniqid());

        return [
            'submission' => $submission,
            'author' => $author,
            'editor' => $editor,
            'reviewerA' => User::query()->create([
                'given_name' => 'Reviewer',
                'family_name' => 'A',
                'email' => 'reviewer-a-'.uniqid().'@example.test',
                'password' => 'password123456',
            ]),
            'reviewerB' => User::query()->create([
                'given_name' => 'Reviewer',
                'family_name' => 'B',
                'email' => 'reviewer-b-'.uniqid().'@example.test',
                'password' => 'password123456',
            ]),
            'reviewerC' => User::query()->create([
                'given_name' => 'Reviewer',
                'family_name' => 'C',
                'email' => 'reviewer-c-'.uniqid().'@example.test',
                'password' => 'password123456',
            ]),
        ];
    }

    protected function createReviewerRole(): Role
    {
        return Role::query()->firstOrCreate([
            'name' => UserRole::Reviewer->value,
            'conference_id' => app()->getCurrentConferenceId(),
            'scheduled_conference_id' => app()->getCurrentScheduledConferenceId(),
            'guard_name' => 'web',
        ]);
    }

    protected function createAuthorRole(): Role
    {
        $role = Role::query()->firstOrCreate([
            'name' => UserRole::Author->value,
            'conference_id' => app()->getCurrentConferenceId(),
            'scheduled_conference_id' => app()->getCurrentScheduledConferenceId(),
            'guard_name' => 'web',
        ]);

        Permission::query()->firstOrCreate([
            'name' => 'Submission:uploadPaper',
            'guard_name' => 'web',
        ]);

        $role->syncPermissions(['Submission:uploadPaper']);

        return $role;
    }

    protected function assignEditorRole(User $user, Submission $submission): Role
    {
        $editorRole = Role::withoutGlobalScopes()
            ->where('name', UserRole::ScheduledConferenceEditor->value)
            ->where('conference_id', app()->getCurrentConferenceId())
            ->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId())
            ->firstOrFail();

        $user->assignRole($editorRole);
        $submission->participants()->firstOrCreate([
            'user_id' => $user->getKey(),
            'role_id' => $editorRole->getKey(),
        ]);

        return $editorRole;
    }

    protected function createSubmissionMedia(Submission $submission, string $collection, string $name): Media
    {
        return Media::query()->create([
            'model_type' => Submission::class,
            'model_id' => $submission->getKey(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => $collection,
            'name' => $name,
            'file_name' => $name.'.pdf',
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
    }
}
