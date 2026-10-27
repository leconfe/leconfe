<?php

namespace Tests\Feature\Multilingual;

use App\Mail\Templates\PublishSubmissionMail;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\MailTemplate;
use App\Models\Proceeding;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\User;
use App\Panel\Conference\Resources\ProceedingResource\Pages\ManageProceedings;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ViewSubmission;
use App\Providers\PanelProvider;
use App\Support\Multilingual\LanguageReadiness;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class PublicationLanguageReadinessTest extends TestCase
{
    use InteractsWithContentLanguages;
    use MakesPapers;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);
        $this->enableLanguages(['en', 'fr'], default: 'fr');

        Gate::before(fn () => true);

        $this->author = $this->makeAuthor();
        $this->author->forceFill(['locale' => 'fr'])->save();
        $this->actingAs($this->author);

        app()->setLocale('fr');
    }

    public function test_paper_publish_dialog_summarises_languages_and_still_publishes(): void
    {
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));
        Mail::fake();

        $this->publishTemplate();
        $paper = $this->partiallyTranslatedPaper($this->unpublishedProceeding());

        Livewire::test(ViewSubmission::class, ['record' => $paper->getKey()])
            ->mountAction('publish')
            ->assertSee('Langue de l’article : English.')
            ->assertSee('La langue de la conférence est French. Les articles rédigés dans une autre langue peuvent tout de même être publiés.')
            ->assertSee('French : aucune traduction pour Résumé, Mots-clés. Les lecteurs verront le texte d’origine (English).')
            ->assertDontSee('Sous-titre')
            ->assertSee('E-mail de notification à l’auteur : French.')
            ->assertDontSee('Le modèle d’e-mail n’est pas traduit')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(SubmissionStatus::Published, $paper->refresh()->status);
    }

    public function test_paper_publish_dialog_warns_when_the_email_template_is_not_in_the_author_language(): void
    {
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));

        $this->publishTemplate();
        $this->author->forceFill(['locale' => 'en'])->save();
        $paper = $this->partiallyTranslatedPaper($this->unpublishedProceeding());

        Livewire::test(ViewSubmission::class, ['record' => $paper->getKey()])
            ->mountAction('publish')
            ->assertSee('E-mail de notification à l’auteur : English.')
            ->assertSee('Le modèle d’e-mail n’est pas traduit dans cette langue : le texte utilisé sera la version « French ».');
    }

    public function test_proceeding_publish_dialog_summarises_its_metadata_and_papers(): void
    {
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_CONFERENCE));

        $proceeding = $this->unpublishedProceeding();
        $this->partiallyTranslatedPaper($proceeding);

        Livewire::test(ManageProceedings::class)
            ->mountTableAction('publish', $proceeding)
            ->assertSee('English : aucune traduction pour Titre, Description. Les lecteurs verront le texte d’origine (French).')
            ->assertSee('French : 1 article de ces actes a des métadonnées non traduites. Les lecteurs le verront dans sa langue de rédaction.')
            ->callMountedTableAction();

        $this->assertTrue((bool) $proceeding->refresh()->published);
    }

    public function test_proceeding_publish_dialog_counts_untranslated_papers_and_translated_ones(): void
    {
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_CONFERENCE));

        $proceeding = $this->unpublishedProceeding();
        $proceeding->setTranslation('title', 'en', 'Proceedings 2026');
        $proceeding->setTranslation('description', 'en', '<p>Selected papers</p>');
        $this->partiallyTranslatedPaper($proceeding);
        $this->partiallyTranslatedPaper($proceeding);
        $this->makePaper($this->author, 'fr', [
            'title' => ['fr' => 'Récifs coralliens', 'en' => 'Coral reefs'],
        ], SubmissionStage::Editing, SubmissionStatus::Editing, $proceeding);

        Livewire::test(ManageProceedings::class)
            ->mountTableAction('publish', $proceeding)
            ->assertSee('English : toutes les métadonnées sont traduites.')
            ->assertSee('English : les métadonnées de tous les articles sont traduites.')
            ->assertSee('French : 2 articles de ces actes ont des métadonnées non traduites. Les lecteurs les verront dans leur langue de rédaction.');
    }

    public function test_proceeding_publish_dialog_shows_no_summary_for_a_single_language(): void
    {
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_CONFERENCE));
        $this->enableLanguages(['fr'], default: 'fr');

        $proceeding = $this->unpublishedProceeding();
        $this->makePaper($this->author, 'fr', ['title' => ['fr' => 'Récifs coralliens']], SubmissionStage::Editing, SubmissionStatus::Editing, $proceeding);

        Livewire::test(ManageProceedings::class)
            ->mountTableAction('publish', $proceeding)
            ->assertSee('Êtes-vous sûr de vouloir faire cela ?')
            ->assertDontSeeHtml('data-language-readiness');
    }

    public function test_proceeding_summary_query_count_does_not_grow_with_its_papers(): void
    {
        $proceeding = $this->unpublishedProceeding();
        $this->partiallyTranslatedPaper($proceeding);
        $single = $this->countSummaryQueries($proceeding);

        $this->partiallyTranslatedPaper($proceeding);
        $this->partiallyTranslatedPaper($proceeding);

        $this->assertSame($single, $this->countSummaryQueries($proceeding));
    }

    protected function countSummaryQueries(Proceeding $proceeding): int
    {
        $proceeding = $proceeding->fresh();
        LanguageReadiness::forProceeding($proceeding);

        DB::flushQueryLog();
        DB::enableQueryLog();
        LanguageReadiness::forProceeding($proceeding);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
    protected function partiallyTranslatedPaper(Proceeding $proceeding): Submission
    {
        return $this->makePaper($this->author, 'en', [
            'title' => ['en' => 'Coral reefs', 'fr' => 'Récifs coralliens'],
            'abstract' => ['en' => '<p>A study of coral reefs</p>'],
            'keywords' => ['en' => ['reef']],
        ], SubmissionStage::Editing, SubmissionStatus::Editing, $proceeding);
    }

    protected function unpublishedProceeding(): Proceeding
    {
        return Proceeding::query()->create([
            'title' => 'Actes 2026',
            'description' => '<p>Articles choisis</p>',
            'volume' => 1,
            'year' => 2026,
            'published' => false,
        ]);
    }

    protected function publishTemplate(): MailTemplate
    {
        return MailTemplate::query()->create([
            'conference_id' => $this->conference->getKey(),
            'mailable' => PublishSubmissionMail::class,
            'subject' => 'Soumission publiée',
            'html_template' => '<p>Votre soumission a été publiée.</p>',
        ]);
    }
}
