<?php

namespace Tests\Feature\Multilingual;

use App\Actions\Submissions\SubmissionCreateAction;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Submissions\Forms\Detail;
use App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Steps\DetailStep;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class SubmissionPaperLanguageTest extends TestCase
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

        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));

        // Permissions are covered elsewhere; these tests cover the paper language.
        Gate::before(fn () => true);

        $this->makeTrack();
        $this->author = $this->makeAuthor();
        $this->actingAs($this->author);
    }

    public function test_wizard_defaults_to_the_site_default_and_stores_the_chosen_language(): void
    {
        $this->enableLanguages(['en', 'id', 'fr'], default: 'fr');

        $submission = SubmissionCreateAction::run(['meta' => ['title' => 'Coral reefs']]);

        $this->assertSame('fr', $submission->getMeta('language'));

        Livewire::test(DetailStep::class, ['record' => $submission])
            ->assertSee(__('general.paper_language'))
            ->assertSet('meta.language', 'fr')
            ->set('meta.language', 'id')
            ->set('meta.title.id', 'Terumbu karang')
            ->set('meta.abstract.id', '<p>Kajian terumbu karang</p>')
            ->callAction('nextStep')
            ->assertHasNoErrors();

        $submission = Submission::query()->find($submission->getKey());

        $this->assertSame('id', $submission->getMeta('language'));
        $this->assertSame('id', $submission->getPrimaryContentLocale());
        $this->assertSame('Terumbu karang', $submission->getMeta('title'));
    }

    public function test_the_language_moves_typed_metadata_to_the_new_paper_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        $submission = SubmissionCreateAction::run(['meta' => ['title' => 'Coral reefs']]);

        Livewire::test(DetailStep::class, ['record' => $submission])
            ->assertSet('meta.title.en', 'Coral reefs')
            ->set('meta.language', 'id')
            ->assertSet('meta.title.id', 'Coral reefs')
            ->assertSet('meta.title.en', null);
    }

    public function test_the_language_field_is_hidden_with_one_language(): void
    {
        $submission = SubmissionCreateAction::run(['meta' => ['title' => 'Coral reefs']]);

        $this->assertSame('en', $submission->getMeta('language'));

        Livewire::test(DetailStep::class, ['record' => $submission])
            ->assertDontSee(__('general.paper_language'))
            ->assertSet('meta.title', 'Coral reefs')
            ->set('meta.abstract', '<p>A study of reefs</p>')
            ->callAction('nextStep')
            ->assertHasNoErrors();

        $this->assertSame('en', Submission::query()->find($submission->getKey())->getMeta('language'));

        Livewire::test(Detail::class, ['submission' => $submission])
            ->assertDontSee(__('general.paper_language'));
    }

    public function test_the_detail_form_changes_the_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        $submission = $this->makePaper($this->author, 'en', [
            'title' => ['en' => 'Coral reefs', 'id' => 'Terumbu karang'],
            'abstract' => ['en' => '<p>A study of reefs</p>', 'id' => '<p>Kajian terumbu karang</p>'],
        ], SubmissionStage::PeerReview, SubmissionStatus::OnReview);

        Livewire::test(Detail::class, ['submission' => $submission])
            ->assertSet('meta.language', 'en')
            ->set('meta.language', 'id')
            ->call('submit')
            ->assertHasNoErrors();

        $submission = Submission::query()->find($submission->getKey());

        $this->assertSame('id', $submission->getPrimaryContentLocale());
        $this->assertSame('Terumbu karang', $submission->getMeta('title'));
        $this->assertSame('<p>Kajian terumbu karang</p>', $submission->getMeta('abstract'));
        $this->assertSame('Coral reefs', $submission->getStoredTranslation('title', 'en'));
    }

    public function test_a_submission_without_a_language_uses_the_site_default(): void
    {
        $this->enableLanguages(['en', 'id'], default: 'id');

        $submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $this->author->getKey(),
            'conference_id' => $this->conference->getKey(),
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'track_id' => $this->makeTrack()->getKey(),
        ]);
        $submission->setManyMeta(['title' => 'Terumbu karang']);

        $this->assertNull($submission->getMeta('language'));
        $this->assertSame('id', $submission->getPrimaryContentLocale());
        $this->assertSame('Terumbu karang', $submission->translated('title', 'en'));

        $submission->setMeta('language', 'xx');

        $this->assertSame('id', $submission->getPrimaryContentLocale());
    }

    public function test_a_paper_in_a_disabled_language_still_renders_its_primary_metadata(): void
    {
        $this->enableLanguages(['en', 'id']);
        $this->conference->setMeta('primary_citation_format', 'apa');

        $paper = $this->makePaper($this->author, 'id', [
            'title' => ['id' => 'Terumbu karang'],
            'abstract' => ['id' => '<p>Kajian terumbu karang</p>'],
            'keywords' => ['id' => ['laut', 'karang']],
        ], SubmissionStage::Proceeding, SubmissionStatus::Published, $this->makeProceeding());

        $this->enableLanguages(['en']);

        $paper = Submission::query()->find($paper->getKey());

        $this->assertSame('id', $paper->getPrimaryContentLocale());
        $this->assertSame(['en' => 'English', 'id' => 'Indonesian'], $paper->getLanguageOptions());
        $this->assertSame(['id', 'en'], $paper->getContentLocalesForForm());

        $this->visitPaper($this->conference, $paper, 'en')
            ->assertSee('Terumbu karang')
            ->assertSee('Kajian terumbu karang', false)
            ->assertSee('karang')
            ->assertSee('<meta name="citation_language" content="id">', false);

        Livewire::test(Detail::class, ['submission' => $paper])
            ->assertSet('meta.title.id', 'Terumbu karang')
            ->assertSet('meta.keywords.id', ['laut', 'karang'])
            ->call('submit')
            ->assertHasNoErrors();

        $paper = Submission::query()->find($paper->getKey());

        $this->assertSame('id', $paper->getPrimaryContentLocale());
        $this->assertSame('Terumbu karang', $paper->getStoredTranslation('title', 'id'));
        $this->assertSame(['laut', 'karang'], $paper->getStoredTranslation('keywords', 'id'));
    }
}
