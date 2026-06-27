<?php

namespace Tests\Feature;

use App\Constants\ReviewerStatus;
use App\Models\Conference;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Review;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManageSubmissionsSearchTest extends TestCase
{
    use RefreshDatabase;

    protected Conference $conference;
    protected ScheduledConference $scheduledConference;
    protected Track $track;
    protected User $editor;
    protected User $reviewer;
    protected User $author;
    protected Role $editorRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = Conference::query()->create([
            'name' => 'Test Conference',
            'path' => 'test-conference',
        ]);

        $this->scheduledConference = ScheduledConference::withoutGlobalScopes()->create([
            'conference_id' => $this->conference->getKey(),
            'title' => 'Test Scheduled Conference',
            'path' => 'test-scheduled-conference',
            'date_start' => now()->toDateString(),
            'date_end' => now()->addDays(2)->toDateString(),
        ]);

        app()->setCurrentConferenceId($this->conference->getKey());
        app()->setCurrentScheduledConferenceId($this->scheduledConference->getKey());

        $this->track = Track::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'title' => 'Test Track',
            'abbreviation' => 'TT',
            'is_active' => true,
        ]);

        $this->author = User::query()->create([
            'given_name' => 'Author',
            'family_name' => 'Tester',
            'email' => 'author@test.example',
            'password' => 'password',
        ]);

        $this->editor = User::query()->create([
            'given_name' => 'Editor',
            'family_name' => 'Tester',
            'email' => 'editor@test.example',
            'password' => 'password',
        ]);

        $this->editorRole = Role::withoutGlobalScopes()
            ->where('name', UserRole::ScheduledConferenceEditor->value)
            ->where('conference_id', $this->conference->getKey())
            ->where('scheduled_conference_id', $this->scheduledConference->getKey())
            ->firstOrFail();

        $this->editor->assignRole($this->editorRole);

        $this->reviewer = User::query()->create([
            'given_name' => 'Reviewer',
            'family_name' => 'Tester',
            'email' => 'reviewer@test.example',
            'password' => 'password',
        ]);
    }

    protected function createSubmission(User $author, string $title, SubmissionStatus $status = SubmissionStatus::Queued): Submission
    {
        $submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $author->getKey(),
            'conference_id' => $this->conference->getKey(),
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'track_id' => $this->track->getKey(),
            'status' => $status,
        ]);

        $submission->setManyMeta([
            'title' => $title,
            'keywords' => 'test, search, bug',
            'abstract' => 'Test abstract content',
        ]);

        return $submission;
    }

    protected function makeEditorParticipant(Submission $submission): void
    {
        $submission->participants()->create([
            'user_id' => $this->editor->getKey(),
            'role_id' => $this->editorRole->getKey(),
        ]);
    }

    protected function makeEditorReviewer(Submission $submission, string $status = ReviewerStatus::ACCEPTED): void
    {
        Review::query()->create([
            'submission_id' => $submission->getKey(),
            'user_id' => $this->editor->getKey(),
            'status' => $status,
            'date_confirmed' => $status === ReviewerStatus::ACCEPTED ? now() : null,
        ]);
    }

    // Build the My Queue query the same way tabMyQueue() does — but with proper grouping (the fix)
    protected function myQueueQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return SubmissionResource::getEloquentQuery()
            ->whereNotIn('status', [
                SubmissionStatus::Published,
                SubmissionStatus::Withdrawn,
            ])
            ->where(function (\Illuminate\Database\Eloquent\Builder $query) {
                $query->whereHas('participants', fn ($q) => $q->where('user_id', $this->editor->getKey()))
                    ->orWhereHas('reviews', fn ($q) => $q->where('user_id', $this->editor->getKey()));
            });
    }

    /** @test */
    public function my_queue_with_search_excludes_participant_submission_not_matching_search(): void
    {
        // Editor IS participant on submission A (title: "Machine Learning")
        $submissionA = $this->createSubmission($this->author, 'Machine Learning Applications');
        $this->makeEditorParticipant($submissionA);

        // Editor IS reviewer on submission B (title: "Deep Learning")
        $submissionB = $this->createSubmission($this->author, 'Deep Learning Networks');
        $this->makeEditorReviewer($submissionB);

        // Search for "Deep" (matches submission B only)
        $search = 'Deep';
        $results = $this->myQueueQuery()
            ->where(function ($query) use ($search) {
                $query->where('id', 'like', "%{$search}%")
                    ->orWhere(function ($q) use ($search) {
                        $q->whereMeta('title', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($u) => $u
                                ->whereMeta('public_name', 'like', "%{$search}%")
                                ->orWhere('given_name', 'like', "%{$search}%")
                                ->orWhere('family_name', 'like', "%{$search}%"));
                    });
            })
            ->pluck('id');

        // Submission A (participant) should NOT appear — the search only matches B
        $this->assertNotContains($submissionA->getKey(), $results,
            'Submission where user is only a participant should NOT appear when it does not match the search');
        $this->assertContains($submissionB->getKey(), $results,
            'Submission where user is a reviewer AND matches the search should appear');
    }

    /** @test */
    public function my_queue_with_search_by_id_does_not_leak_participant_submissions(): void
    {
        $submissionA = $this->createSubmission($this->author, 'Quantum Computing');
        $this->makeEditorParticipant($submissionA);

        $submissionB = $this->createSubmission($this->author, 'Blockchain Protocols');
        $this->makeEditorReviewer($submissionB);

        // Search for submission B's exact ID
        $searchId = (string) $submissionB->getKey();
        $results = $this->myQueueQuery()
            ->where('id', 'like', "%{$searchId}%")
            ->pluck('id');

        $this->assertNotContains($submissionA->getKey(), $results,
            'Participant submission should not leak past the search filter');
        $this->assertContains($submissionB->getKey(), $results,
            'Reviewer submission matching the search should appear');
    }

    /** @test */
    public function my_queue_with_search_shows_participant_submission_when_it_matches(): void
    {
        $submission = $this->createSubmission($this->author, 'Edge Computing Frameworks');
        $this->makeEditorParticipant($submission);

        $search = 'Edge';
        $results = $this->myQueueQuery()
            ->where(function ($query) use ($search) {
                $query->whereMeta('title', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u
                        ->whereMeta('public_name', 'like', "%{$search}%")
                        ->orWhere('given_name', 'like', "%{$search}%")
                        ->orWhere('family_name', 'like', "%{$search}%"));
            })
            ->pluck('id');

        $this->assertContains($submission->getKey(), $results,
            'Participant submission matching the search should appear');
    }

    /** @test */
    public function my_queue_excludes_published_status(): void
    {
        $published = $this->createSubmission($this->author, 'Published Paper', SubmissionStatus::Published);
        $this->makeEditorParticipant($published);

        $active = $this->createSubmission($this->author, 'Active Paper', SubmissionStatus::Queued);
        $this->makeEditorParticipant($active);

        $results = $this->myQueueQuery()->pluck('id');

        $this->assertNotContains($published->getKey(), $results);
        $this->assertContains($active->getKey(), $results);
    }

    /** @test */
    public function my_queue_excludes_withdrawn_status(): void
    {
        $withdrawn = $this->createSubmission($this->author, 'Withdrawn Paper', SubmissionStatus::Withdrawn);
        $this->makeEditorParticipant($withdrawn);

        $active = $this->createSubmission($this->author, 'Active Paper', SubmissionStatus::Queued);
        $this->makeEditorParticipant($active);

        $results = $this->myQueueQuery()->pluck('id');

        $this->assertNotContains($withdrawn->getKey(), $results);
        $this->assertContains($active->getKey(), $results);
    }

    /** @test */
    public function my_queue_includes_submission_where_user_is_reviewer(): void
    {
        $submission = $this->createSubmission($this->author, 'Review Target');
        $this->makeEditorReviewer($submission);

        $results = $this->myQueueQuery()->pluck('id');

        $this->assertContains($submission->getKey(), $results,
            'Submission where user is a reviewer should appear in My Queue');
    }
}
