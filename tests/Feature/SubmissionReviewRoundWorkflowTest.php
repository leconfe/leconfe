<?php

namespace Tests\Feature;

use App\Actions\SubmissionFiles\UploadSubmissionFileAction;
use App\Actions\Submissions\CloneSubmissionFilesToReviewRoundAction;
use App\Actions\Submissions\NotifySubmissionRevisionRequestAction;
use App\Actions\Submissions\StartSubmissionReviewRoundAction;
use App\Actions\Submissions\SubmissionUpdateAction;
use App\Constants\ReviewerStatus;
use App\Constants\SubmissionFileCategory;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Media;
use App\Models\Role;
use App\Models\Review;
use App\Models\Permission;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\SubmissionReviewRound;
use App\Models\SubmissionFile;
use App\Models\SubmissionFileType;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\PaperFiles;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ReviewSubmissionPage;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ReviewerInvitationPage;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SubmissionReviewRoundWorkflowTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_paper_files_are_assigned_to_the_active_review_round(): void
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
            'collection_name' => SubmissionFileCategory::PAPER_FILES,
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
            SubmissionFileCategory::PAPER_FILES,
            $type,
            $round->getKey(),
        );

        $this->assertDatabaseHas('submission_files', [
            'submission_id' => $context['submission']->getKey(),
            'media_id' => $media->getKey(),
            'review_round_id' => $round->getKey(),
            'category' => SubmissionFileCategory::PAPER_FILES,
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

        $context['editor']->assignRole($this->createAuthorRole());

        $media = Media::query()->create([
            'model_type' => Submission::class,
            'model_id' => $context['submission']->getKey(),
            'uuid' => (string) Str::uuid(),
            'collection_name' => SubmissionFileCategory::PAPER_FILES,
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
            SubmissionFileCategory::PAPER_FILES,
            $type,
            $round->getKey(),
        );

        $file = SubmissionFile::query()
            ->where('submission_id', $context['submission']->getKey())
            ->where('review_round_id', $round->getKey())
            ->firstOrFail();

        Livewire::test(PaperFiles::class, ['submission' => $context['submission']])
            ->callTableAction('rename', $file, data: [
                'name' => 'renamed-paper',
            ])
            ->assertHasNoTableActionErrors();

        $media->refresh();

        $this->assertSame('renamed-paper', $media->name);
        $this->assertSame('paper-round-one.pdf', $media->file_name);
        $this->assertSame('renamed-paper.pdf', $media->original_file_name);
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
            'collection_name' => SubmissionFileCategory::PAPER_FILES,
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
            SubmissionFileCategory::PAPER_FILES,
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
        $this->assertSame(SubmissionFileCategory::PAPER_FILES, $clonedFile->category);
        $this->assertDatabaseHas('submission_files', [
            'id' => $clonedFile->getKey(),
            'submission_id' => $context['submission']->getKey(),
            'review_round_id' => $secondRound->getKey(),
            'category' => SubmissionFileCategory::PAPER_FILES,
        ]);
        $this->assertContains($clonedFile->getKey(), $secondRound->default_file_ids);
    }

    public function test_selected_revision_files_are_cloned_as_paper_files_in_the_next_review_round(): void
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
            'collection_name' => SubmissionFileCategory::PAPER_FILES,
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
            SubmissionFileCategory::PAPER_FILES,
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

        $this->assertSame(SubmissionFileCategory::PAPER_FILES, $clonedFile->category);
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
            'collection_name' => SubmissionFileCategory::PAPER_FILES,
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
            SubmissionFileCategory::PAPER_FILES,
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

        $page = new class extends ReviewSubmissionPage {
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

        $page = new class extends ReviewerInvitationPage {
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
}
