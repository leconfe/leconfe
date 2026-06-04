<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\Enums\SubmissionStatus;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ScheduledConferenceSubmissionCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_submission_count_excludes_incomplete_submissions(): void
    {
        $conference = Conference::query()->create([
            'name' => 'Test Conference',
            'path' => 'test-conference',
        ]);

        $scheduledConference = ScheduledConference::query()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Test Scheduled Conference',
            'path' => 'test-scheduled-conference',
        ]);

        $track = Track::query()->create([
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'title' => 'General',
            'abbreviation' => 'GEN',
        ]);

        $author = User::factory()->create([
            'password' => Hash::make('password12345'),
        ]);

        Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $author->getKey(),
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'track_id' => $track->getKey(),
            'status' => SubmissionStatus::Incomplete,
        ]);

        Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $author->getKey(),
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'track_id' => $track->getKey(),
            'status' => SubmissionStatus::Queued,
        ]);

        $scheduledConference->loadCount('submittedSubmissions');

        $this->assertSame(1, $scheduledConference->submitted_submissions_count);
        $this->assertSame(
            $scheduledConference->submitted_submissions_count,
            $scheduledConference->submittedSubmissions()->count()
        );
    }
}
