<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\AuthorRole;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
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

    public function test_wizard_owner_can_add_themselves_as_contributor(): void
    {
        $context = $this->makeSubmissionContext();
        $context['user']->setManyMeta([
            'public_name' => 'Published Author',
            'affiliation' => 'Conference University',
            'country' => 'id',
            'phone' => '+628123456789',
        ]);
        $authorRole = $this->createAuthorRole($context['conference']);

        $this->actingAs($context['user']);

        Livewire::test(ContributorList::class, ['submission' => $context['submission']])
            ->assertTableActionVisible('addMyselfAsContributor')
            ->callTableAction('addMyselfAsContributor')
            ->assertHasNoTableActionErrors();

        $author = Author::query()
            ->where('submission_id', $context['submission']->getKey())
            ->where('email', $context['user']->email)
            ->firstOrFail();

        $this->assertSame($authorRole->getKey(), $author->author_role_id);
        $this->assertSame('Author', $author->given_name);
        $this->assertSame('Tester', $author->family_name);
        $this->assertSame('Published Author', $author->getMeta('public_name'));
        $this->assertSame('Conference University', $author->getMeta('affiliation'));
        $this->assertSame('id', $author->getMeta('country'));
        $this->assertSame('+628123456789', $author->getMeta('phone'));
        $this->assertSame($author->getKey(), $context['submission']->fresh()->getMeta('primary_contact_id'));
    }

    public function test_add_myself_as_contributor_is_idempotent(): void
    {
        $context = $this->makeSubmissionContext();
        $this->createAuthorRole($context['conference']);

        $this->actingAs($context['user']);

        $component = Livewire::test(ContributorList::class, ['submission' => $context['submission']]);

        $component
            ->callTableAction('addMyselfAsContributor')
            ->assertHasNoTableActionErrors();

        $component->instance()->addMyselfAsContributor();

        $this->assertSame(
            1,
            Author::query()
                ->where('submission_id', $context['submission']->getKey())
                ->where('email', $context['user']->email)
                ->count()
        );
    }

    public function test_add_myself_as_contributor_action_hides_in_view_only_mode(): void
    {
        $context = $this->makeSubmissionContext();
        $this->createAuthorRole($context['conference']);

        $this->actingAs($context['user']);

        Livewire::test(ContributorList::class, [
            'submission' => $context['submission'],
            'viewOnly' => true,
        ])
            ->assertTableActionHidden('addMyselfAsContributor');
    }

    public function test_add_myself_as_contributor_action_hides_for_non_owner(): void
    {
        $context = $this->makeSubmissionContext();
        $this->createAuthorRole($context['conference']);

        $otherUser = User::query()->create([
            'given_name' => 'Other',
            'family_name' => 'Author',
            'email' => 'other-author@example.test',
            'password' => 'password123456',
        ]);

        $this->actingAs($otherUser);

        Livewire::test(ContributorList::class, ['submission' => $context['submission']])
            ->assertTableActionHidden('addMyselfAsContributor');
    }

    public function test_add_myself_as_contributor_action_hides_when_author_already_exists(): void
    {
        $context = $this->makeSubmissionContext();
        $authorRole = $this->createAuthorRole($context['conference']);

        $this->actingAs($context['user']);

        $context['submission']->authors()->create([
            'author_role_id' => $authorRole->getKey(),
            'given_name' => $context['user']->given_name,
            'family_name' => $context['user']->family_name,
            'email' => $context['user']->email,
        ]);

        Livewire::test(ContributorList::class, ['submission' => $context['submission']])
            ->assertTableActionHidden('addMyselfAsContributor');
    }

    public function test_add_myself_as_contributor_action_hides_after_wizard_stage(): void
    {
        $submittedContext = $this->makeSubmissionContext([
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnReview,
        ]);
        $this->createAuthorRole($submittedContext['conference']);
        $this->actingAs($submittedContext['user']);

        Livewire::test(ContributorList::class, ['submission' => $submittedContext['submission']])
            ->assertTableActionHidden('addMyselfAsContributor');
    }

    protected function makeSubmissionContext(array $submissionOverrides = []): array
    {
        $conference = Conference::query()->create([
            'name' => 'Conference '.uniqid(),
            'path' => 'conference-'.uniqid(),
        ]);

        $scheduledConference = ScheduledConference::withoutGlobalScopes()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Scheduled Conference '.uniqid(),
            'path' => 'scheduled-conference-'.uniqid(),
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
            'email' => 'author-'.uniqid().'@example.test',
            'password' => 'password123456',
        ]);

        $submission = Submission::withoutGlobalScopes()->forceCreate(array_merge([
            'user_id' => $user->getKey(),
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'track_id' => $track->getKey(),
        ], $submissionOverrides));

        return [
            'conference' => $conference,
            'user' => $user,
            'submission' => $submission,
        ];
    }

    protected function createAuthorRole(Conference $conference): AuthorRole
    {
        return AuthorRole::query()->firstOrCreate([
            'conference_id' => $conference->getKey(),
            'name' => 'Author',
        ]);
    }
}
