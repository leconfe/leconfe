<?php

namespace Tests\Feature;

use App\Actions\Submissions\UpdateSubmissionPublicationDateAction;
use App\Events\Submissions\Published;
use App\Managers\CitationManager;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\SubmissionProceeding;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class SubmissionPublicationDateTest extends TestCase
{
    use InteractsWithContentLanguages;
    use MakesPapers;
    use RefreshDatabase;

    private Conference $conference;

    private ScheduledConference $event;

    private Submission $paper;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.installed' => true]);

        $this->enableLanguages(['en', 'id', 'fr']);
        $this->conference = $this->makeConference();
        $this->event = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->event);
        Permission::query()->firstOrCreate(['name' => 'Submission:submitAs', 'guard_name' => 'web']);

        $this->paper = $this->makePaper(
            $this->makeAuthor(),
            'en',
            ['title' => ['en' => 'Publication date corrections']],
            SubmissionStage::Proceeding,
            SubmissionStatus::Published,
            $this->makeProceeding(),
        );
        $this->paper->update(['published_at' => '2027-02-02 08:30:00']);

        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));
        Event::fake([Published::class]);
    }

    #[DataProvider('authorizedRoles')]
    public function test_manager_and_scheduled_editor_can_correct_the_date_after_unpublishing(UserRole $role): void
    {
        $staff = $this->userWithRole($role);
        $this->actingAs($staff);
        $proceedingDate = $this->paper->proceeding->published_at->toDateTimeString();
        $this->paper->state()->unpublish();

        Livewire::test(SubmissionProceeding::class, ['submission' => $this->paper])
            ->assertSee(__('general.publication_date_helper'))
            ->assertFormSet(['published_at' => fn ($date): bool => Carbon::parse($date)->toDateString() === '2027-02-02'])
            ->assertFormFieldIsEnabled('published_at')
            ->fillForm(['published_at' => '2025-11-10'])
            ->call('submit')
            ->assertHasNoFormErrors()
            ->assertNotified(__('general.saved'));

        $paper = $this->paper->fresh();
        $this->assertSame('2025-11-10', $paper->published_at->toDateString());
        $this->assertSame(SubmissionStatus::Editing, $paper->status);
        $this->assertSame(SubmissionStage::Editing, $paper->stage);
        $this->assertSame($this->paper->proceeding_id, $paper->proceeding_id);
        $this->assertSame($proceedingDate, $paper->proceeding->published_at->toDateTimeString());
        $this->assertNull($paper->getMeta('isbn'));

        $log = Activity::query()
            ->where('subject_type', Submission::class)
            ->where('subject_id', $paper->id)
            ->where('description', __('general.submission_publication_date_updated'))
            ->sole();
        $this->assertSame($staff->id, $log->causer_id);
        $this->assertSame('2027-02-02', $log->properties->get('old')['published_at']);
        $this->assertSame('2025-11-10', $log->properties->get('attributes')['published_at']);

        Event::assertNotDispatched(Published::class);

        $paper->state()->publish();
        $this->visitPaper($this->conference, $paper->fresh(), 'en')->assertSee('10 November 2025');
        $this->assertSame('2025-11-10', app(CitationManager::class)->getCitationData($paper->fresh())->issued->raw);
    }

    public static function authorizedRoles(): array
    {
        return [[UserRole::ConferenceManager], [UserRole::ScheduledConferenceEditor]];
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_other_roles_cannot_save_a_publication_date_even_by_direct_request(UserRole $role): void
    {
        $this->setEditing();
        $this->actingAs($this->userWithRole($role));
        $this->assertFalse(Gate::allows('updatePublicationDate', $this->paper));

        Livewire::test(SubmissionProceeding::class, ['submission' => $this->paper])
            ->assertFormFieldIsDisabled('published_at');

        try {
            UpdateSubmissionPublicationDateAction::run($this->paper, '2025-11-10');
            $this->fail('A role without publication date access was allowed to save.');
        } catch (AuthorizationException) {
            $this->assertNull($this->paper->fresh()->published_at);
        }
    }

    public static function unauthorizedRoles(): array
    {
        return [
            [UserRole::TrackEditor],
            [UserRole::Author],
            [UserRole::Reviewer],
            [UserRole::Participant],
            [UserRole::Admin],
        ];
    }

    #[DataProvider('foreignRoleScopes')]
    public function test_a_staff_role_in_another_scope_does_not_grant_access(UserRole $role, bool $otherConference): void
    {
        $this->setEditing();
        $conference = $otherConference
            ? $this->makeConference('Other conference', 'other-conference')
            : $this->conference;
        $event = $this->makeScheduledConference($conference, 'Other event', 'other-event');
        $this->actingAs($this->userWithRole($role, $event));

        $this->assertFalse(Gate::allows('updatePublicationDate', $this->paper));
        $this->expectException(AuthorizationException::class);
        UpdateSubmissionPublicationDateAction::run($this->paper, '2025-11-10');
    }

    public static function foreignRoleScopes(): array
    {
        return [
            [UserRole::ScheduledConferenceEditor, false],
            [UserRole::ScheduledConferenceEditor, true],
            [UserRole::ConferenceManager, true],
        ];
    }

    public function test_access_is_checked_again_when_a_role_is_revoked_after_opening_the_form(): void
    {
        $this->setEditing();
        $staff = $this->userWithRole(UserRole::ScheduledConferenceEditor);
        $this->actingAs($staff);
        $component = Livewire::test(SubmissionProceeding::class, ['submission' => $this->paper])
            ->fillForm(['published_at' => '2025-11-10']);

        $staff->roles()->detach();
        $staff->unsetRelation('roles');

        $component->call('submit')->assertFormFieldIsDisabled('published_at');
        $this->assertNull($this->paper->fresh()->published_at);
        $this->expectException(AuthorizationException::class);
        UpdateSubmissionPublicationDateAction::run($this->paper, '2025-11-10');
    }

    public function test_track_editor_can_edit_draft_metadata_but_cannot_inject_a_publication_date(): void
    {
        $this->setEditing();
        $editor = $this->userWithRole(UserRole::TrackEditor);
        $this->paper->participants()->create([
            'user_id' => $editor->id,
            'role_id' => $editor->roles()->firstOrFail()->id,
        ]);
        $this->actingAs($editor);

        Livewire::test(SubmissionProceeding::class, ['submission' => $this->paper])
            ->assertFormFieldIsDisabled('published_at')
            ->fillForm(['published_at' => '2025-11-10', 'meta.isbn' => '978-1-2345-6789-0'])
            ->call('submit')
            ->assertHasNoFormErrors()
            ->assertNotified(__('general.saved'));

        $this->assertNull($this->paper->fresh()->published_at);
        $this->assertSame('978-1-2345-6789-0', $this->paper->fresh()->getMeta('isbn'));
    }

    #[DataProvider('authorizedRoles')]
    public function test_date_changes_preserve_existing_proceeding_metadata_and_cover(UserRole $role): void
    {
        $this->setEditing();
        Storage::fake('media-library');
        Queue::fake();
        $this->paper->setManyMeta(['isbn' => '978-1-2345-6789-0', 'article_pages' => '12-18']);
        $cover = $this->paper->addMedia(resource_path('assets/images/placeholder-vertical.jpg'))
            ->preservingOriginal()
            ->toMediaCollection('cover', 'media-library');
        $trackId = $this->paper->track_id;
        $this->actingAs($this->userWithRole($role));

        Livewire::test(SubmissionProceeding::class, ['submission' => $this->paper])
            ->fillForm(['published_at' => '2025-11-10'])
            ->call('submit')
            ->assertHasNoFormErrors()
            ->assertNotified(__('general.saved'));

        $paper = $this->paper->fresh();
        $this->assertSame('2025-11-10', $paper->published_at->toDateString());
        $this->assertSame($trackId, $paper->track_id);
        $this->assertSame('978-1-2345-6789-0', $paper->getMeta('isbn'));
        $this->assertSame('12-18', $paper->getMeta('article_pages'));
        $this->assertSame($cover->id, $paper->getFirstMedia('cover')?->id);
        Storage::disk('media-library')->assertExists($cover->getPathRelativeToRoot());
    }

    #[DataProvider('authorizedRoles')]
    public function test_other_proceeding_fields_can_still_be_saved_alongside_the_date(UserRole $role): void
    {
        $this->setEditing();
        $track = Track::query()->create(['title' => 'New track', 'abbreviation' => 'NEW']);
        $this->actingAs($this->userWithRole($role));

        Livewire::test(SubmissionProceeding::class, ['submission' => $this->paper])
            ->assertFormFieldIsEnabled('track_id')
            ->fillForm([
                'published_at' => '2025-11-10',
                'track_id' => $track->id,
                'meta.isbn' => '978-1-2345-6789-0',
                'meta.article_pages' => '12-18',
            ])
            ->call('submit')
            ->assertHasNoFormErrors()
            ->assertNotified(__('general.saved'));

        $paper = $this->paper->fresh();
        $this->assertSame('2025-11-10', $paper->published_at->toDateString());
        $this->assertSame($track->id, $paper->track_id);
        $this->assertSame('978-1-2345-6789-0', $paper->getMeta('isbn'));
        $this->assertSame('12-18', $paper->getMeta('article_pages'));
    }

    public function test_a_backdated_draft_keeps_its_date_when_published_and_republished(): void
    {
        $this->setEditing();
        $this->actingAs($this->userWithRole(UserRole::ConferenceManager));

        Livewire::test(SubmissionProceeding::class, ['submission' => $this->paper])
            ->fillForm(['published_at' => '2025-11-10'])
            ->call('submit')
            ->assertHasNoFormErrors();

        $paper = $this->paper->fresh();
        $paper->state()->publish();
        $this->assertSame('2025-11-10', $paper->fresh()->published_at->toDateString());

        $paper->state()->unpublish();
        $paper->state()->publish();
        $this->assertSame('2025-11-10', $paper->fresh()->published_at->toDateString());
    }

    public function test_an_empty_draft_date_is_set_automatically_on_publication(): void
    {
        $this->setEditing();
        $this->travelTo(Carbon::parse('2026-11-10 09:30:00'));
        $this->actingAs($this->userWithRole(UserRole::ScheduledConferenceEditor));

        Livewire::test(SubmissionProceeding::class, ['submission' => $this->paper])
            ->fillForm(['published_at' => null])
            ->call('submit')
            ->assertHasNoFormErrors();

        $paper = $this->paper->fresh();
        $this->assertNull($paper->published_at);
        $paper->state()->publish();
        $this->assertSame('2026-11-10 09:30:00', $paper->fresh()->published_at->toDateTimeString());
    }

    public function test_a_draft_date_can_be_cleared_to_restore_automatic_dating(): void
    {
        $this->setEditing();
        $this->paper->update(['published_at' => '2025-11-10']);
        $this->actingAs($this->userWithRole(UserRole::ScheduledConferenceEditor));

        Livewire::test(SubmissionProceeding::class, ['submission' => $this->paper])
            ->fillForm(['published_at' => null])
            ->call('submit')
            ->assertHasNoFormErrors();

        $this->assertNull($this->paper->fresh()->published_at);
    }

    #[DataProvider('authorizedRoles')]
    public function test_a_published_paper_date_is_locked_for_managers_and_scheduled_editors(UserRole $role): void
    {
        $this->actingAs($this->userWithRole($role));
        $this->assertFalse(Gate::allows('updatePublicationDate', $this->paper));

        Livewire::test(SubmissionProceeding::class, ['submission' => $this->paper])
            ->assertFormFieldIsDisabled('published_at')
            ->fillForm(['published_at' => '2025-11-10'])
            ->call('submit')
            ->assertForbidden();

        $this->assertSame('2027-02-02', $this->paper->fresh()->published_at->toDateString());
        $this->expectException(AuthorizationException::class);
        UpdateSubmissionPublicationDateAction::run($this->paper, '2025-11-10');
    }

    #[DataProvider('authorizedRoles')]
    public function test_publishing_locks_an_open_form_and_rejects_a_stale_save(UserRole $role): void
    {
        $this->setEditing();
        $this->actingAs($this->userWithRole($role));
        $component = Livewire::test(SubmissionProceeding::class, ['submission' => $this->paper])
            ->assertFormFieldIsEnabled('published_at')
            ->fillForm(['published_at' => '2025-11-10']);
        $stalePaper = $this->paper->fresh();

        $this->paper->state()->publish();
        $publicationDate = $this->paper->fresh()->published_at->toDateTimeString();

        $component->call('submit')->assertForbidden();
        $this->assertSame($publicationDate, $this->paper->fresh()->published_at->toDateTimeString());

        $this->expectException(AuthorizationException::class);
        UpdateSubmissionPublicationDateAction::run($stalePaper, '2025-11-10');
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_dates_are_rejected_at_the_save_boundary(?string $date): void
    {
        $this->setEditing();
        $this->actingAs($this->userWithRole(UserRole::ScheduledConferenceEditor));

        try {
            UpdateSubmissionPublicationDateAction::run($this->paper, $date);
            $this->fail('An invalid publication date was saved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('published_at', $exception->errors());
            $this->assertNull($this->paper->fresh()->published_at);
        }
    }

    public static function invalidDates(): array
    {
        return [['not-a-date'], ['2025-02-30'], ['2025-11-10 13:00:00']];
    }

    #[DataProvider('publicationStates')]
    public function test_publication_date_access_follows_the_paper_lifecycle(SubmissionStatus $status, bool $allowed): void
    {
        $this->paper->update(['status' => $status]);
        $this->actingAs($this->userWithRole(UserRole::ScheduledConferenceEditor));

        $this->assertSame($allowed, Gate::allows('updatePublicationDate', $this->paper));
    }

    public static function publicationStates(): array
    {
        return [
            [SubmissionStatus::Queued, true],
            [SubmissionStatus::OnReview, true],
            [SubmissionStatus::OnPresentation, true],
            [SubmissionStatus::Editing, true],
            [SubmissionStatus::Published, false],
            [SubmissionStatus::Declined, false],
            [SubmissionStatus::Withdrawn, false],
        ];
    }

    private function setEditing(): void
    {
        $this->paper->update([
            'stage' => SubmissionStage::Editing,
            'status' => SubmissionStatus::Editing,
            'published_at' => null,
        ]);
    }

    private function userWithRole(UserRole $role, ?ScheduledConference $event = null): User
    {
        $event ??= $this->event;
        $roleModel = Role::withoutGlobalScopes()->firstOrCreate([
            'name' => $role->value,
            'guard_name' => 'web',
            'conference_id' => $role === UserRole::Admin ? 0 : $event->conference_id,
            'scheduled_conference_id' => in_array($role, [UserRole::Admin, UserRole::ConferenceManager], true) ? 0 : $event->id,
        ]);
        $user = User::factory()->create(['password' => 'password123456']);
        $user->assignRole($roleModel);

        return $user;
    }
}
