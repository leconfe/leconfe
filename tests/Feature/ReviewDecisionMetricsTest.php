<?php

namespace Tests\Feature;

use App\Constants\ReviewerStatus;
use App\Constants\SubmissionStatusRecommendation;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Review;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewDecisionMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_decision_metrics_exclude_canceled_and_declined_reviews(): void
    {
        $context = $this->makeSubmissionContext();

        $this->createReview($context['submission'], $context['acceptedReviewer'], [
            'status' => ReviewerStatus::ACCEPTED,
            'date_confirmed' => now()->subDays(2),
            'date_completed' => now()->subDay(),
            'recommendation' => SubmissionStatusRecommendation::ACCEPT,
            'score' => 90,
        ]);
        $this->createReview($context['submission'], $context['canceledReviewer'], [
            'status' => ReviewerStatus::CANCELED,
            'date_confirmed' => now()->subDays(2),
            'date_completed' => now()->subDay(),
            'recommendation' => SubmissionStatusRecommendation::DECLINE,
            'score' => 10,
        ]);
        $this->createReview($context['submission'], $context['declinedReviewer'], [
            'status' => ReviewerStatus::DECLINED,
            'date_confirmed' => now()->subDay(),
        ]);
        $this->createReview($context['submission'], $context['pendingReviewer'], [
            'status' => ReviewerStatus::PENDING,
            'date_confirmed' => null,
        ]);

        $metrics = Submission::query()
            ->whereKey($context['submission']->getKey())
            ->withCount([
                'reviews as reviews_count' => fn ($query) => $query->activeAssignments(),
                'reviews as completed_reviews_count' => fn ($query) => $query->submittedForDecision(),
            ])
            ->withAvg(['reviews' => fn ($query) => $query->submittedForDecision()], 'score')
            ->firstOrFail();

        $this->assertSame(2, $metrics->reviews_count);
        $this->assertSame(1, $metrics->completed_reviews_count);
        $this->assertSame(90.0, (float) $metrics->reviews_avg_score);
    }

    public function test_review_summary_email_uses_only_submitted_decision_reviews(): void
    {
        $context = $this->makeSubmissionContext();

        $this->createReview($context['submission'], $context['acceptedReviewer'], [
            'status' => ReviewerStatus::ACCEPTED,
            'date_confirmed' => now()->subDays(2),
            'date_completed' => now()->subDay(),
            'recommendation' => SubmissionStatusRecommendation::ACCEPT,
            'score' => 90,
            'meta' => [
                'review_mode' => Review::MODE_OPEN,
                'review_for_author_editor' => 'Accepted review should be visible.',
            ],
        ]);
        $this->createReview($context['submission'], $context['canceledReviewer'], [
            'status' => ReviewerStatus::CANCELED,
            'date_confirmed' => now()->subDays(2),
            'date_completed' => now()->subDay(),
            'recommendation' => SubmissionStatusRecommendation::DECLINE,
            'score' => 10,
            'meta' => [
                'review_mode' => Review::MODE_OPEN,
                'review_for_author_editor' => 'Canceled review should be hidden.',
            ],
        ]);

        $message = $context['submission']->getReviewsEmailMessage();

        $this->assertStringContainsString('Accepted review should be visible.', $message);
        $this->assertStringNotContainsString('Canceled review should be hidden.', $message);
    }

    private function makeSubmissionContext(): array
    {
        $conference = Conference::factory()->create([
            'path' => 'review-metrics-conference',
        ]);
        $scheduledConference = ScheduledConference::factory()->create([
            'conference_id' => $conference->getKey(),
            'path' => 'review-metrics-scheduled-conference',
        ]);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());

        $track = Track::withoutGlobalScopes()
            ->where('scheduled_conference_id', $scheduledConference->getKey())
            ->firstOrFail();

        $author = User::factory()->create([
            'email' => 'metrics-author@example.test',
            'password' => 'password123456',
        ]);
        $reviewers = collect([
            'acceptedReviewer' => 'accepted-reviewer@example.test',
            'canceledReviewer' => 'canceled-reviewer@example.test',
            'declinedReviewer' => 'declined-reviewer@example.test',
            'pendingReviewer' => 'pending-reviewer@example.test',
        ])->map(fn (string $email) => User::factory()->create([
            'email' => $email,
            'password' => 'password123456',
        ]));

        $reviewerRole = Role::withoutGlobalScopes()
            ->where('name', UserRole::Reviewer->value)
            ->where('conference_id', $conference->getKey())
            ->where('scheduled_conference_id', $scheduledConference->getKey())
            ->firstOrFail();

        $reviewers->each(fn (User $reviewer) => $reviewer->assignRole($reviewerRole));

        $submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $author->getKey(),
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'track_id' => $track->getKey(),
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnReview,
        ]);
        $submission->setManyMeta([
            'title' => 'Review metrics submission',
        ]);

        return [
            'conference' => $conference,
            'scheduledConference' => $scheduledConference,
            'track' => $track,
            'author' => $author,
            'submission' => $submission,
            ...$reviewers->all(),
        ];
    }

    private function createReview(Submission $submission, User $reviewer, array $attributes = []): Review
    {
        $meta = $attributes['meta'] ?? [];
        unset($attributes['meta']);

        $review = Review::query()->create(array_merge([
            'submission_id' => $submission->getKey(),
            'user_id' => $reviewer->getKey(),
            'status' => ReviewerStatus::PENDING,
            'date_assigned' => now(),
        ], $attributes));

        if ($meta) {
            $review->setManyMeta($meta);
        }

        return $review->refresh();
    }
}
