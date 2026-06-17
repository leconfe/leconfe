<?php

namespace Tests\Feature;

use App\Actions\Submissions\StartSubmissionReviewRoundAction;
use App\Constants\ReviewerStatus;
use App\Constants\SubmissionFileCategory;
use App\Constants\SubmissionStatusRecommendation;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Permission;
use App\Models\Review;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\SubmissionFileType;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ReviewerList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewerAssignmentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test/submissions/{record}/review', fn () => null)
            ->name('filament.conference.resources.submissions.review');
    }

    public function test_pending_reviewer_can_be_unassigned_and_removed_from_assignment_history(): void
    {
        Mail::fake();

        $context = $this->makeReviewerContext();
        $pendingReview = $this->createReview($context['submission'], $context['reviewer'], [
            'status' => ReviewerStatus::PENDING,
            'date_confirmed' => null,
        ]);
        $assignedFile = $this->createAssignedFile($context['submission'], $pendingReview);

        $this->actingAs($context['editor']);

        Livewire::test(ReviewerList::class, ['record' => $context['submission']->refresh()])
            ->assertTableActionVisible('unassign-reviewer', $pendingReview)
            ->assertTableActionHidden('cancel-reviewer', $pendingReview)
            ->callTableAction('unassign-reviewer', $pendingReview, data: [
                'do-not-notify-cancelation' => true,
            ]);

        $this->assertDatabaseMissing('reviews', [
            'id' => $pendingReview->getKey(),
        ]);
        $this->assertDatabaseMissing('reviewer_assigned_files', [
            'id' => $assignedFile->getKey(),
        ]);

        $context['submission']->reviews()->create([
            'review_round_id' => $context['reviewRound']->getKey(),
            'user_id' => $context['reviewer']->getKey(),
            'date_assigned' => now(),
        ]);

        $this->assertDatabaseHas('reviews', [
            'submission_id' => $context['submission']->getKey(),
            'user_id' => $context['reviewer']->getKey(),
            'status' => ReviewerStatus::PENDING,
        ]);
    }

    public function test_accepted_reviewer_can_be_canceled_without_losing_review_history(): void
    {
        Mail::fake();

        $context = $this->makeReviewerContext();
        $completedAt = now()->subDay()->startOfSecond();
        $confirmedAt = now()->subDays(2)->startOfSecond();
        $acceptedReview = $this->createReview($context['submission'], $context['reviewer'], [
            'status' => ReviewerStatus::ACCEPTED,
            'date_confirmed' => $confirmedAt,
            'date_completed' => $completedAt,
            'recommendation' => SubmissionStatusRecommendation::ACCEPT,
            'score' => 88,
            'quality' => 5,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(ReviewerList::class, ['record' => $context['submission']->refresh()])
            ->assertTableActionHidden('unassign-reviewer', $acceptedReview)
            ->assertTableActionVisible('cancel-reviewer', $acceptedReview)
            ->callTableAction('cancel-reviewer', $acceptedReview, data: [
                'do-not-notify-cancelation' => true,
            ]);

        $acceptedReview->refresh();

        $this->assertSame(ReviewerStatus::CANCELED, $acceptedReview->status);
        $this->assertEquals($confirmedAt, $acceptedReview->date_confirmed);
        $this->assertEquals($completedAt, $acceptedReview->date_completed);
        $this->assertSame(SubmissionStatusRecommendation::ACCEPT, $acceptedReview->recommendation);
        $this->assertSame(88, $acceptedReview->score);
        $this->assertSame(5, $acceptedReview->quality);
    }

    public function test_declined_reviewer_stays_as_history_without_unassign_or_cancel_actions(): void
    {
        $context = $this->makeReviewerContext();
        $declinedReview = $this->createReview($context['submission'], $context['reviewer'], [
            'status' => ReviewerStatus::DECLINED,
            'date_confirmed' => now()->subDay(),
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(ReviewerList::class, ['record' => $context['submission']->refresh()])
            ->assertTableActionHidden('unassign-reviewer', $declinedReview)
            ->assertTableActionHidden('cancel-reviewer', $declinedReview);

        $this->assertDatabaseHas('reviews', [
            'id' => $declinedReview->getKey(),
            'status' => ReviewerStatus::DECLINED,
        ]);
    }

    public function test_reinstating_canceled_reviewers_restores_their_original_commitment_state(): void
    {
        $context = $this->makeReviewerContext();
        $acceptedCancellation = $this->createReview($context['submission'], $context['reviewer'], [
            'status' => ReviewerStatus::CANCELED,
            'date_confirmed' => now()->subDays(2),
            'date_completed' => now()->subDay(),
            'recommendation' => SubmissionStatusRecommendation::ACCEPT,
            'score' => 80,
        ]);
        $legacyReviewer = User::factory()->create([
            'email' => 'legacy-reviewer@example.test',
            'password' => 'password123456',
        ]);
        $legacyReviewer->assignRole($context['reviewerRole']);
        $legacyCancellation = $this->createReview($context['submission'], $legacyReviewer, [
            'status' => ReviewerStatus::CANCELED,
            'date_confirmed' => null,
        ]);

        $this->actingAs($context['editor']);

        Livewire::test(ReviewerList::class, ['record' => $context['submission']->refresh()])
            ->callTableAction('reinstate-reviewer', $acceptedCancellation)
            ->callTableAction('reinstate-reviewer', $legacyCancellation);

        $this->assertSame(ReviewerStatus::ACCEPTED, $acceptedCancellation->refresh()->status);
        $this->assertSame(ReviewerStatus::PENDING, $legacyCancellation->refresh()->status);
    }

    private function makeReviewerContext(): array
    {
        $conference = Conference::factory()->create([
            'path' => 'reviewer-flow-conference',
        ]);
        $scheduledConference = ScheduledConference::factory()->create([
            'conference_id' => $conference->getKey(),
            'path' => 'reviewer-flow-scheduled-conference',
        ]);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());

        $track = Track::withoutGlobalScopes()
            ->where('scheduled_conference_id', $scheduledConference->getKey())
            ->firstOrFail();

        $author = User::factory()->create([
            'email' => 'author@example.test',
            'password' => 'password123456',
        ]);
        $editor = User::factory()->create([
            'email' => 'editor@example.test',
            'password' => 'password123456',
        ]);
        $reviewer = User::factory()->create([
            'email' => 'reviewer@example.test',
            'password' => 'password123456',
        ]);

        $editorRole = Role::withoutGlobalScopes()
            ->where('name', UserRole::ScheduledConferenceEditor->value)
            ->where('conference_id', $conference->getKey())
            ->where('scheduled_conference_id', $scheduledConference->getKey())
            ->firstOrFail();
        $reviewerRole = Role::withoutGlobalScopes()
            ->where('name', UserRole::Reviewer->value)
            ->where('conference_id', $conference->getKey())
            ->where('scheduled_conference_id', $scheduledConference->getKey())
            ->firstOrFail();

        Permission::query()->firstOrCreate([
            'name' => 'Submission:reinstateReviewer',
            'guard_name' => 'web',
        ]);

        $editor->assignRole($editorRole);
        $reviewer->assignRole($reviewerRole);

        $submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $author->getKey(),
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'track_id' => $track->getKey(),
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnReview,
        ]);
        $submission->setManyMeta([
            'title' => 'Reviewer assignment flow submission',
        ]);
        $submission->participants()->create([
            'user_id' => $editor->getKey(),
            'role_id' => $editorRole->getKey(),
        ]);
        $reviewRound = StartSubmissionReviewRoundAction::run($submission, [], $editor);
        $submission->refresh();

        return [
            'conference' => $conference,
            'scheduledConference' => $scheduledConference,
            'track' => $track,
            'author' => $author,
            'editor' => $editor,
            'reviewer' => $reviewer,
            'reviewerRole' => $reviewerRole,
            'reviewRound' => $reviewRound,
            'submission' => $submission,
        ];
    }

    private function createReview(Submission $submission, User $reviewer, array $attributes = []): Review
    {
        $meta = $attributes['meta'] ?? [];
        unset($attributes['meta']);

        $review = Review::query()->create(array_merge([
            'submission_id' => $submission->getKey(),
            'review_round_id' => $submission->activeReviewRound()->value('id'),
            'user_id' => $reviewer->getKey(),
            'status' => ReviewerStatus::PENDING,
            'date_assigned' => now(),
        ], $attributes));

        if ($meta) {
            $review->setManyMeta($meta);
        }

        return $review->refresh();
    }

    private function createAssignedFile(Submission $submission, Review $review)
    {
        $submissionFileType = SubmissionFileType::withoutGlobalScopes()
            ->where('scheduled_conference_id', $submission->scheduled_conference_id)
            ->firstOrFail();

        $mediaId = DB::table('media')->insertGetId([
            'model_type' => Submission::class,
            'model_id' => $submission->getKey(),
            'collection_name' => 'paper-files',
            'name' => 'paper',
            'file_name' => 'paper.pdf',
            'mime_type' => 'application/pdf',
            'disk' => 'private-files',
            'conversions_disk' => 'private-files',
            'size' => 100,
            'manipulations' => json_encode([]),
            'custom_properties' => json_encode([]),
            'generated_conversions' => json_encode([]),
            'responsive_images' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $submissionFile = SubmissionFile::query()->create([
            'submission_id' => $submission->getKey(),
            'review_round_id' => $review->review_round_id,
            'submission_file_type_id' => $submissionFileType->getKey(),
            'media_id' => $mediaId,
            'user_id' => $submission->user_id,
            'category' => SubmissionFileCategory::PAPER_FILES,
        ]);

        return $review->assignedFiles()->create([
            'submission_file_id' => $submissionFile->getKey(),
        ]);
    }
}
