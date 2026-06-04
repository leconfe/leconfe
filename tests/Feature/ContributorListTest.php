<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ContributorList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContributorListTest extends TestCase
{
    use RefreshDatabase;

    public function test_contributor_form_does_not_offer_existing_author_selection(): void
    {
        $context = $this->makeSubmissionContext();

        $this->actingAs($context['user']);

        Livewire::test(ContributorList::class, ['submission' => $context['submission']])
            ->assertFormFieldDoesNotExist('author_id')
            ->assertFormFieldExists('given_name')
            ->assertFormFieldExists('email');
    }

    protected function makeSubmissionContext(): array
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

        $user = User::query()->create([
            'given_name' => 'Author',
            'family_name' => 'Tester',
            'email' => 'author@example.test',
            'password' => 'password123456',
        ]);

        $submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $user->getKey(),
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'track_id' => $track->getKey(),
        ]);

        return [
            'user' => $user,
            'submission' => $submission,
        ];
    }
}
