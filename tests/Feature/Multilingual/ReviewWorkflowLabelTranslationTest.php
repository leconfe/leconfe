<?php

namespace Tests\Feature\Multilingual;

use App\Actions\Submissions\StartSubmissionReviewRoundAction;
use App\Constants\ReviewerStatus;
use App\Constants\SubmissionStatusRecommendation;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Review;
use App\Models\Enums\UserRole;
use App\Panel\Conference\Resources\UserResource\Pages\ListUsers;
use App\Panel\ScheduledConference\Livewire\Submissions\CallforAbstract;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\AbstractFiles;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ParticipantList;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ReviewerInvitationPage;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ReviewSubmissionPage;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ViewSubmission;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\SubmissionFormItem;
use App\Models\Track;
use App\Models\User;
use App\Panel\Administration\Pages\PluginManagement;
use App\Panel\Administration\Pages\SystemInformation;
use App\Panel\ScheduledConference\Livewire\SubmissionFormItemTable;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ReviewerList;
use App\Panel\ScheduledConference\Pages\Presentations;
use App\Panel\ScheduledConference\Pages\ReviewResult;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewWorkflowLabelTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = Conference::factory()->create(['path' => 'labels-conference']);
        $this->scheduledConference = ScheduledConference::factory()->create([
            'conference_id' => $this->conference->getKey(),
            'path' => 'labels-scheduled',
        ]);
        app()->setCurrentConferenceId($this->conference->getKey());
        app()->setCurrentScheduledConferenceId($this->scheduledConference->getKey());

        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));
        URL::defaults(['conference' => $this->conference->path, 'serie' => $this->scheduledConference->path]);
        Gate::before(fn () => true);

        $this->actingAs(User::factory()->create(['email' => 'labels-editor@example.test', 'password' => 'password123456']));

        app()->setLocale('fr');
    }

    public function test_recommendation_options_are_french_and_keep_their_stored_values(): void
    {
        $this->assertSame([
            'Accept' => 'Accepter',
            'Decline' => 'Refuser',
            'Revision Required' => 'Révisions requises',
        ], SubmissionStatusRecommendation::list());
    }

    public function test_an_editor_reads_a_submitted_review_in_french(): void
    {
        $submission = $this->submissionOnReview();
        $review = $this->submittedReview($submission);

        Livewire::test(ReviewerList::class, ['record' => $submission])
            ->loadTable()
            ->assertSee('Révisions requises')
            ->assertSee('Accepté')
            ->assertSee('Lire l’évaluation')
            ->mountTableAction('read-review', $review)
            ->assertSee('Recommandation : Révisions requises')
            ->assertSee('Commentaires de l’évaluateur')
            ->assertSee('Note de l’évaluateur')
            ->assertDontSee('Reviewer Comments');

        $this->assertSame(SubmissionStatusRecommendation::REVISION_REQUIRED, $review->fresh()->recommendation);
    }

    public function test_the_user_list_shows_role_badges_in_the_interface_language(): void
    {
        $editor = User::factory()->create(['email' => 'labels-track-editor@example.test', 'password' => 'password123456']);
        $editor->assignRole(UserRole::TrackEditor);
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_CONFERENCE));
        $this->withoutVite();

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$editor])
            ->assertSee('Responsable d’axe thématique');

        app()->setLocale('en');

        Livewire::test(ListUsers::class)
            ->assertSee('Track Editor');
    }

    public function test_editor_decisions_titles_and_participants_are_french(): void
    {
        $submission = $this->submissionOnReview();

        Livewire::test(CallforAbstract::class, ['submission' => $submission])
            ->assertActionHasLabel('decline', 'Décliner');

        $this->assertSame('Évaluer la soumission', (new ReviewSubmissionPage)->getTitle());
        $this->assertSame('Invitation à évaluer', (new ReviewerInvitationPage)->getTitle());
        $this->assertSame('Voir', (new ViewSubmission)->getBreadcrumb());

        Livewire::test(ParticipantList::class, ['submission' => $submission])
            ->loadTable()
            ->assertSee('Créer un(e) Participant')
            ->assertDontSee('submission participant')
            ->mountTableAction('create')
            ->assertSee('Rôle');

        Livewire::test(AbstractFiles::class, ['submission' => $submission])
            ->mountTableAction('upload')
            ->assertSee('Fichiers')
            ->assertDontSee('>Files<', false);
    }

    public function test_the_english_recommendation_badge_keeps_its_wording(): void
    {
        $submission = $this->submissionOnReview();
        $this->submittedReview($submission);

        app()->setLocale('en');

        Livewire::test(ReviewerList::class, ['record' => $submission])
            ->loadTable()
            ->assertSee('Recommend Revision Required');
    }

    public function test_review_result_shows_french_column_and_status_labels(): void
    {
        $submission = $this->submissionOnReview();
        $this->submittedReview($submission);
        $submission->update(['status' => SubmissionStatus::Published]);

        Livewire::test(ReviewResult::class)
            ->loadTable()
            ->assertSee('Note')
            ->assertSee('Évaluations')
            ->assertSee('Statut')
            ->assertSee('Publié')
            ->assertDontSee('Published');
    }

    public function test_presentations_empty_state_and_form_item_options_are_french(): void
    {
        Livewire::test(Presentations::class)
            ->assertSee('Rechercher un titre')
            ->assertSee('Aucune présentation trouvée');

        Livewire::test(SubmissionFormItemTable::class)
            ->mountTableAction('create')
            ->assertSee('Créer élément de formulaire')
            ->assertSee('Nom de l’élément')
            ->assertSee('Type d’élément')
            ->assertSee('Liste déroulante')
            ->setTableActionData(['type' => SubmissionFormItem::TYPE_SELECT])
            ->assertSee('Options de réponse');
    }

    public function test_administration_pages_are_french(): void
    {
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_ADMINISTRATION));

        $this->assertSame('Gestion des plugins', (new PluginManagement)->getTitle());

        Livewire::test(PluginManagement::class)
            ->assertSee('Plugins installés')
            ->assertSee('Galerie de plugins');

        Livewire::test(SystemInformation::class)
            ->assertSee('Version actuelle')
            ->assertSee('Historique des versions')
            ->assertSee('Date d’installation');
    }

    private function submittedReview(Submission $submission): Review
    {
        return Review::query()->create([
            'submission_id' => $submission->getKey(),
            'review_round_id' => $submission->activeReviewRound()->value('id'),
            'user_id' => User::factory()->create(['email' => 'labels-reviewer@example.test', 'password' => 'password123456'])->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'date_assigned' => now(),
            'date_completed' => now(),
            'recommendation' => SubmissionStatusRecommendation::REVISION_REQUIRED,
            'score' => 4,
        ]);
    }

    private function submissionOnReview(): Submission
    {
        $submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => User::factory()->create(['email' => 'labels-author@example.test', 'password' => 'password123456'])->getKey(),
            'conference_id' => $this->conference->getKey(),
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'track_id' => Track::query()->firstOrFail()->getKey(),
            'stage' => SubmissionStage::PeerReview,
            'status' => SubmissionStatus::OnReview,
        ]);
        $submission->setManyMeta(['title' => 'Labels submission']);
        StartSubmissionReviewRoundAction::run($submission);

        return $submission;
    }
}
