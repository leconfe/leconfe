<?php

namespace Tests\Feature\Multilingual;

use App\Actions\Submissions\StartSubmissionReviewRoundAction;
use App\Frontend\Conference\Pages\ProceedingDetail;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\DraftFiles;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\RevisionFiles;
use App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard;
use App\Panel\ScheduledConference\Pages\WorkflowSetting;
use App\Providers\PanelProvider;
use DOMDocument;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class EditorialTerminologyTest extends TestCase
{
    use InteractsWithContentLanguages;
    use MakesPapers;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);
        $this->enableLanguages(['en', 'fr']);

        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));
        Gate::before(fn () => true);

        $this->user = $this->makeAuthor();
        $this->actingAs($this->user);
        app()->setLocale('fr');
    }

    public function test_peer_review_settings_tab_uses_evaluation_in_french(): void
    {
        Livewire::test(WorkflowSetting::class)
            ->assertSee('Évaluation par les pairs')
            ->assertDontSee('Vérification');
    }

    public function test_pre_submit_wizard_step_uses_verification_in_french(): void
    {
        Livewire::test(SubmissionWizard::class, ['record' => $this->makeSubmission()])
            ->assertSee('Vérification')
            ->assertDontSee('Évaluation');
    }

    public static function frenchLocales(): array
    {
        return [['fr'], ['fr_CA']];
    }

    /**
     * @dataProvider frenchLocales
     */
    public function test_file_upload_date_column_uses_televersement(string $locale): void
    {
        app()->setLocale($locale);

        $label = Livewire::test(DraftFiles::class, ['submission' => $this->makeSubmission()])
            ->instance()
            ->getTable()
            ->getColumn('created_at')
            ->getLabel();

        $this->assertSame('Date de téléversement', $label);
    }

    /**
     * @dataProvider frenchLocales
     */
    public function test_revision_upload_notice_uses_televerses(string $locale): void
    {
        app()->setLocale($locale);

        $submission = $this->makeSubmission(SubmissionStage::PeerReview, SubmissionStatus::OnReview);
        StartSubmissionReviewRoundAction::run($submission);

        Livewire::test(RevisionFiles::class, ['submission' => $submission->refresh()])
            ->mountTableAction('upload')
            ->assertSee('Une fois les fichiers téléversés')
            ->assertDontSee('téléchargés');
    }

    /**
     * @dataProvider frenchLocales
     */
    public function test_failed_upload_validation_message_uses_televersement(string $locale): void
    {
        app()->setLocale($locale);

        $file = new UploadedFile(__FILE__, 'paper.pdf', 'application/pdf', UPLOAD_ERR_PARTIAL, true);
        $message = Validator::make(['paper' => $file], ['paper' => 'file'])->errors()->first('paper');

        $this->assertSame('Le téléversement de paper a échoué.', $message);
    }

    public function test_only_the_active_proceeding_tab_is_marked_current(): void
    {
        $proceeding = $this->makeProceeding();
        $proceeding->setMeta('additional_content', [
            ['title' => 'Editorial board', 'content' => '<p>Board</p>'],
            ['title' => 'Indexing', 'content' => '<p>Indexed</p>'],
        ]);
        $proceeding->save();

        $html = $this->frontend('fr', route(ProceedingDetail::getRouteName('conference'), [
            'conference' => $this->conference->path,
            'proceeding' => $proceeding->getKey(),
        ]))->getContent();

        $document = new DOMDocument;
        @$document->loadHTML($html);
        $tabs = iterator_to_array($document->getElementsByTagName('button'));
        $tabs = array_values(array_filter($tabs, fn ($tab) => str_contains($tab->getAttribute('x-on:click'), 'activeTab=')));

        $this->assertCount(3, $tabs);

        $current = array_values(array_filter($tabs, fn ($tab) => $tab->hasAttribute('aria-current')));
        $this->assertCount(1, $current);
        $this->assertSame("activeTab='home'", $current[0]->getAttribute('x-on:click'));
        $this->assertSame('true', $current[0]->getAttribute('aria-current'));

        foreach ($tabs as $tab) {
            $target = str_replace('activeTab=', '', $tab->getAttribute('x-on:click'));
            $this->assertSame("activeTab == {$target} ? 'true' : null", $tab->getAttribute('x-bind:aria-current'));
        }
    }

    protected function frontend(string $locale, string $url)
    {
        return $this->withoutVite()
            ->withSession(['locale' => $locale])
            ->get($url)
            ->assertOk();
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
}
