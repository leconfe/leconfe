<?php

namespace Tests\Feature\Multilingual;

use App\Actions\Submissions\StartSubmissionReviewRoundAction;
use App\Constants\ReviewerStatus;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Review;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Timeline;
use App\Models\Topic;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Steps\DetailStep;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\CreateSubmission;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ReviewSubmissionPage;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ViewSubmission;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\StoresDefaultLanguageRows;
use Tests\TestCase;

class PanelRenderingTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;
    use StoresDefaultLanguageRows;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);

        $this->enableLanguages(['en', 'id', 'fr']);

        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));

        // Permissions are covered elsewhere; these tests cover the rendered content.
        Gate::before(fn () => true);

        $this->user = User::query()->create([
            'given_name' => 'Panel',
            'family_name' => 'User',
            'email' => 'panel-user@example.test',
            'password' => 'password123456',
        ]);
        $this->actingAs($this->user);
    }

    public function test_wizard_track_choices_and_policy_use_the_ui_language(): void
    {
        Timeline::query()->create([
            'name' => 'Submission open',
            'date' => now()->subDay(),
            'type' => Timeline::TYPE_SUBMISSION_OPEN,
        ]);

        $chemistry = Track::query()->create(['title' => 'Chemistry', 'abbreviation' => 'CHEM']);
        $chemistry->setManyMeta(['policy' => '<p>Chemistry papers only</p>', 'submit_only_for_editors' => false]);
        $chemistry->setTranslation('title', 'id', 'Kimia');
        $chemistry->setTranslation('policy', 'id', '<p>Hanya makalah kimia</p>');
        Track::query()->create(['title' => 'Physics', 'abbreviation' => 'PHY'])->setMeta('submit_only_for_editors', false);
        $biology = Track::query()->create(['title' => 'Stored biology title', 'abbreviation' => 'BIO']);
        $biology->setManyMeta(['policy' => '<p>Stored biology policy</p>', 'submit_only_for_editors' => false]);
        $this->storeDefaultLanguageRow($biology, 'title', 'Biology');
        $this->storeDefaultLanguageRow($biology, 'policy', '<p>Life science papers only</p>');

        app()->setLocale('id');

        Livewire::test(CreateSubmission::class)
            ->assertSee('Kimia')
            ->assertSee('Physics')
            ->assertSee('Biology')
            ->assertDontSee('Stored biology title')
            ->assertDontSee('Chemistry')
            ->set('data.track_id', $chemistry->getKey())
            ->assertSee('Hanya makalah kimia')
            ->assertDontSee('Chemistry papers only')
            ->set('data.track_id', $biology->getKey())
            ->assertSee('Life science papers only')
            ->assertDontSee('Stored biology policy');

        app()->setLocale('fr');

        Livewire::test(CreateSubmission::class)
            ->assertSee('Chemistry')
            ->assertSee('Physics')
            ->assertDontSee('Kimia')
            ->set('data.track_id', $chemistry->getKey())
            ->assertSee('Chemistry papers only');
    }

    public function test_wizard_topic_choices_use_the_ui_language(): void
    {
        $submission = $this->makeSubmission();

        $energy = Topic::query()->create(['name' => 'Energy']);
        $energy->setTranslation('name', 'id', 'Energi');
        $water = Topic::query()->create(['name' => 'Water']);
        $soil = Topic::query()->create(['name' => 'Stored soil name']);
        $this->storeDefaultLanguageRow($soil, 'name', 'Soil');

        app()->setLocale('id');

        $this->assertSame(
            [$energy->getKey() => 'Energi', $water->getKey() => 'Water', $soil->getKey() => 'Soil'],
            $this->topicOptions(Livewire::test(DetailStep::class, ['record' => $submission])),
        );

        app()->setLocale('fr');

        $this->assertSame(
            [$energy->getKey() => 'Energy', $water->getKey() => 'Water', $soil->getKey() => 'Soil'],
            $this->topicOptions(Livewire::test(DetailStep::class, ['record' => $submission])),
        );
    }

    public function test_reviewer_guidance_uses_the_ui_language(): void
    {
        $this->scheduledConference->setManyMeta([
            'review_guidelines' => '<p>Be constructive</p>',
            'competing_interests' => '<p>Declare conflicts</p>',
        ]);
        $this->scheduledConference->setTranslation('review_guidelines', 'id', '<p>Bersikap membangun</p>');
        $this->storeDefaultLanguageRow($this->scheduledConference, 'competing_interests', '<p>Disclose every conflict</p>');

        $submission = $this->makeSubmission(SubmissionStage::PeerReview, SubmissionStatus::OnReview);
        $round = StartSubmissionReviewRoundAction::run($submission, [], $this->user);
        $review = Review::query()->create([
            'submission_id' => $submission->getKey(),
            'review_round_id' => $round->getKey(),
            'user_id' => $this->user->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'date_confirmed' => now(),
        ]);
        $review->setMeta('review_mode', Review::MODE_OPEN);

        // Shared with panel views by the SetupDefaultData middleware.
        View::share('currentScheduledConference', $this->scheduledConference);

        app()->setLocale('id');

        Livewire::test(ReviewSubmissionPage::class, ['record' => $submission])
            ->assertSee('Bersikap membangun')
            ->assertSee('Disclose every conflict')
            ->assertDontSee('Declare conflicts')
            ->assertDontSee('Be constructive');

        app()->setLocale('fr');

        Livewire::test(ReviewSubmissionPage::class, ['record' => $submission])
            ->assertSee('Be constructive')
            ->assertSee('Disclose every conflict')
            ->assertDontSee('Bersikap membangun');
    }

    public function test_reviewer_guidance_uses_legacy_values_without_translations(): void
    {
        $this->scheduledConference->setManyMeta([
            'review_guidelines' => '<p>Legacy review guidelines</p>',
            'competing_interests' => '<p>Legacy competing interests</p>',
        ]);

        $submission = $this->makeSubmission(SubmissionStage::PeerReview, SubmissionStatus::OnReview);
        $round = StartSubmissionReviewRoundAction::run($submission, [], $this->user);
        $review = Review::query()->create([
            'submission_id' => $submission->getKey(),
            'review_round_id' => $round->getKey(),
            'user_id' => $this->user->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'date_confirmed' => now(),
        ]);
        $review->setMeta('review_mode', Review::MODE_OPEN);

        View::share('currentScheduledConference', $this->scheduledConference);

        app()->setLocale('id');

        Livewire::test(ReviewSubmissionPage::class, ['record' => $submission])
            ->assertSee('Legacy review guidelines')
            ->assertSee('Legacy competing interests');
    }

    public function test_editor_guidance_uses_the_ui_language(): void
    {
        $this->scheduledConference->setMeta('editor_guidelines', '<p>Check the scope</p>');
        $this->scheduledConference->setTranslation('editor_guidelines', 'id', '<p>Periksa cakupan</p>');

        $submission = $this->makeSubmission(SubmissionStage::PeerReview, SubmissionStatus::OnReview);
        $editorRole = Role::withoutGlobalScopes()->firstOrCreate([
            'name' => UserRole::ScheduledConferenceEditor->value,
            'guard_name' => 'web',
            'conference_id' => $this->conference->getKey(),
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
        ]);
        $this->user->assignRole($editorRole);
        $submission->participants()->create(['user_id' => $this->user->getKey(), 'role_id' => $editorRole->getKey()]);

        app()->setLocale('id');

        Livewire::test(ViewSubmission::class, ['record' => $submission->getKey()])
            ->assertSee('Periksa cakupan')
            ->assertDontSee('Check the scope');

        app()->setLocale('fr');

        Livewire::test(ViewSubmission::class, ['record' => $submission->getKey()])
            ->assertSee('Check the scope')
            ->assertDontSee('Periksa cakupan');
    }

    public function test_editor_guidance_prefers_the_default_language_row_over_the_legacy_value(): void
    {
        $this->scheduledConference->setMeta('editor_guidelines', '<p>Stored editor guidance</p>');
        $this->storeDefaultLanguageRow($this->scheduledConference, 'editor_guidelines', '<p>Assign two reviewers</p>');

        $submission = $this->makeSubmission(SubmissionStage::PeerReview, SubmissionStatus::OnReview);
        $editorRole = Role::withoutGlobalScopes()->firstOrCreate([
            'name' => UserRole::ScheduledConferenceEditor->value,
            'guard_name' => 'web',
            'conference_id' => $this->conference->getKey(),
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
        ]);
        $this->user->assignRole($editorRole);
        $submission->participants()->create(['user_id' => $this->user->getKey(), 'role_id' => $editorRole->getKey()]);

        app()->setLocale('id');

        Livewire::test(ViewSubmission::class, ['record' => $submission->getKey()])
            ->assertSee('Assign two reviewers')
            ->assertDontSee('Stored editor guidance');
    }

    public function test_editor_guidance_is_hidden_without_content(): void
    {
        $this->scheduledConference->setMeta('editor_guidelines', '<p></p>');

        $submission = $this->makeSubmission(SubmissionStage::PeerReview, SubmissionStatus::OnReview);
        $editorRole = Role::withoutGlobalScopes()->firstOrCreate([
            'name' => UserRole::ScheduledConferenceEditor->value,
            'guard_name' => 'web',
            'conference_id' => $this->conference->getKey(),
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
        ]);
        $this->user->assignRole($editorRole);
        $submission->participants()->create(['user_id' => $this->user->getKey(), 'role_id' => $editorRole->getKey()]);

        Livewire::test(ViewSubmission::class, ['record' => $submission->getKey()])
            ->assertActionHidden('editor_guidance');
    }

    protected function makeSubmission(SubmissionStage $stage = SubmissionStage::Wizard, SubmissionStatus $status = SubmissionStatus::Incomplete): Submission
    {
        $track = Track::query()->firstOrCreate(['abbreviation' => 'GEN'], ['title' => 'General']);

        $submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $this->user->getKey(),
            'conference_id' => $this->conference->getKey(),
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'track_id' => $track->getKey(),
            'stage' => $stage,
            'status' => $status,
        ]);
        $submission->setManyMeta(['title' => 'A paper about water', 'abstract' => '<p>Water research</p>']);

        return $submission;
    }

    /**
     * @return array<int, string>
     */
    protected function topicOptions(Testable $component): array
    {
        $select = collect($component->instance()->form->getFlatComponents(withHidden: true))
            ->first(fn ($component) => $component instanceof Select && $component->getName() === 'topic');

        return collect($select->getOptions())->all();
    }
}
