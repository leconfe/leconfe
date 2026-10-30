<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Submissions\CallforAbstract;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ParticipantList;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ViewSubmission;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class SubmissionEditorAssignmentRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected ScheduledConference $scheduledConference;

    protected User $manager;

    protected Role $editorRole;

    protected Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        $conference = Conference::query()->create(['name' => 'Conference', 'path' => 'conference']);

        $this->scheduledConference = ScheduledConference::withoutGlobalScopes()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Scheduled Conference',
            'path' => 'scheduled-conference',
            'date_start' => now()->toDateString(),
            'date_end' => now()->addDays(2)->toDateString(),
        ]);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($this->scheduledConference->getKey());

        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));

        Gate::before(fn () => true);

        $this->editorRole = Role::withoutGlobalScopes()
            ->where('name', UserRole::ScheduledConferenceEditor->value)
            ->where('scheduled_conference_id', $this->scheduledConference->getKey())
            ->firstOrFail();

        $this->manager = User::query()->create([
            'given_name' => 'Manager',
            'family_name' => 'Tester',
            'email' => 'manager@example.test',
            'password' => 'password123456',
        ]);
        $this->manager->assignRole($this->editorRole);

        $author = User::query()->create([
            'given_name' => 'Author',
            'family_name' => 'Tester',
            'email' => 'author@example.test',
            'password' => 'password123456',
        ]);

        $track = Track::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'title' => 'Track',
            'abbreviation' => 'TRK',
            'is_active' => true,
        ]);

        $this->submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $author->getKey(),
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'track_id' => $track->getKey(),
            'stage' => SubmissionStage::CallforAbstract,
            'status' => SubmissionStatus::Queued,
        ]);
        $this->submission->setManyMeta(['title' => 'A paper about water']);

        $this->actingAs($this->manager);
    }

    public function test_decision_panel_offers_send_for_review_after_an_editor_is_assigned(): void
    {
        $editor = User::query()->create([
            'given_name' => 'Claire',
            'family_name' => 'Editor',
            'email' => 'claire@example.test',
            'password' => 'password123456',
        ]);
        $editor->assignRole($this->editorRole);

        $decisions = Livewire::test(CallforAbstract::class, ['submission' => $this->submission])
            ->assertSee(__('general.assign_an_editor_to_enable_the_editorial'))
            ->assertDontSee(__('general.send_for_review'));

        $this->assignEditor($editor);

        $decisions
            ->dispatch('refreshSubmission')
            ->assertDontSee(__('general.assign_an_editor_to_enable_the_editorial'))
            ->assertSee(__('general.send_for_review'));
    }

    public function test_page_shows_editor_guidance_after_assigning_yourself_as_editor(): void
    {
        $this->scheduledConference->setMeta('editor_guidelines', '<p>Check the scope first</p>');

        $page = Livewire::test(ViewSubmission::class, ['record' => $this->submission->getKey()])
            ->assertActionHidden('editor_guidance');

        $this->assignEditor($this->manager);

        $page
            ->dispatch('refreshSubmission')
            ->assertActionVisible('editor_guidance');
    }

    protected function assignEditor(User $editor): void
    {
        Livewire::test(ParticipantList::class, ['submission' => $this->submission])
            ->callTableAction('create', data: [
                'role_id' => $this->editorRole->getKey(),
                'user_id' => $editor->getKey(),
                'subject' => 'Assigned',
                'message' => 'You are the editor.',
                'no-notification' => true,
            ])
            ->assertHasNoTableActionErrors()
            ->assertDispatched('refreshSubmission');
    }
}
