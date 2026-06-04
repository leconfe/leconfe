<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ContributorList;
use App\Panel\ScheduledConference\Resources\CommitteeResource\Pages\ManageCommittee;
use App\Panel\ScheduledConference\Resources\SpeakerResource\Pages\ManageSpeakers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
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
            ->assertFormFieldExists('profile', fn ($field): bool => ! array_key_exists(
                'x-on:update-profile-image.window',
                $field->getExtraAlpineAttributes()
            ))
            ->assertFormFieldExists('given_name')
            ->assertFormFieldExists('email');
    }

    public function test_speaker_and_committee_create_forms_do_not_offer_existing_person_selection(): void
    {
        $context = $this->makeSubmissionContext();

        $this->actingAs($context['user']);
        Gate::before(fn () => true);

        Livewire::test(ManageSpeakers::class)
            ->mountTableAction('create')
            ->assertFormFieldDoesNotExist('speaker_id', 'mountedTableActionForm')
            ->assertFormFieldExists('profile', 'mountedTableActionForm', fn ($field): bool => ! array_key_exists(
                'x-on:speaker-profile-image-selected.window',
                $field->getExtraAlpineAttributes()
            ))
            ->assertFormFieldExists('given_name', 'mountedTableActionForm')
            ->assertFormFieldExists('email', 'mountedTableActionForm');

        Livewire::test(ManageCommittee::class)
            ->mountTableAction('create')
            ->assertFormFieldDoesNotExist('committee_id', 'mountedTableActionForm')
            ->assertFormFieldExists('profile', 'mountedTableActionForm', fn ($field): bool => ! array_key_exists(
                'x-on:committee-profile-image-selected.window',
                $field->getExtraAlpineAttributes()
            ))
            ->assertFormFieldExists('given_name', 'mountedTableActionForm')
            ->assertFormFieldExists('email', 'mountedTableActionForm');
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
