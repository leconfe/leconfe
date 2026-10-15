<?php

namespace Tests\Feature\Multilingual;

use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Submissions\Forms\Detail;
use App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Steps\DetailStep;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use RahmanRamsi\LaravelTranslatable\Translate;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class SubmissionMetadataFormsTest extends TestCase
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

        $this->enableLanguages(['en', 'id']);

        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));

        // Permissions are covered elsewhere; these tests cover the metadata forms.
        Gate::before(fn () => true);

        $this->author = $this->makeAuthor();
        $this->actingAs($this->author);
    }

    public function test_wizard_puts_the_paper_language_first_and_requires_it(): void
    {
        $submission = $this->makePaper($this->author, 'id', []);

        Livewire::test(DetailStep::class, ['record' => $submission])
            ->assertSeeHtmlInOrder(['meta.title.id', 'meta.title.en'])
            ->assertSeeHtmlInOrder(['meta.subtitle.id', 'meta.subtitle.en'])
            ->assertSeeHtmlInOrder(['meta.keywords.id', 'meta.keywords.en'])
            ->assertSeeHtmlInOrder(['meta.abstract.id', 'meta.abstract.en'])
            ->set('meta.title.en', 'Coral reefs')
            ->set('meta.abstract.en', '<p>A study of reefs</p>')
            ->callAction('nextStep')
            ->assertHasErrors(['meta.title.id' => 'required', 'meta.abstract.id' => 'required'])
            ->assertHasNoErrors(['meta.title.en', 'meta.abstract.en']);
    }

    public function test_wizard_saves_metadata_per_language(): void
    {
        $submission = $this->makePaper($this->author, 'id', []);

        Livewire::test(DetailStep::class, ['record' => $submission])
            ->set('meta.title', ['id' => 'Terumbu karang', 'en' => 'Coral reefs'])
            ->set('meta.subtitle', ['id' => 'Kajian lapangan', 'en' => 'A field study'])
            ->set('meta.keywords', ['id' => ['laut', 'karang'], 'en' => ['sea', 'coral', 'reef']])
            ->set('meta.abstract', ['id' => '<p>Kajian terumbu karang</p>', 'en' => '<p>A study of reefs</p>'])
            ->callAction('nextStep')
            ->assertHasNoErrors();

        $submission = Submission::query()->find($submission->getKey());

        $this->assertSame('Terumbu karang', $submission->getStoredTranslation('title', 'id'));
        $this->assertSame('Coral reefs', $submission->getStoredTranslation('title', 'en'));
        $this->assertSame('A field study', $submission->getStoredTranslation('subtitle', 'en'));
        $this->assertSame('<p>A study of reefs</p>', $submission->getStoredTranslation('abstract', 'en'));
        $this->assertSame(['sea', 'coral', 'reef'], $submission->getStoredTranslation('keywords', 'en'));
        $this->assertSame(['laut', 'karang'], $submission->getStoredTranslation('keywords', 'id'));

        // The original meta holds the paper-language values.
        $this->assertSame('Terumbu karang', $submission->getMeta('title'));
        $this->assertSame('Kajian lapangan', $submission->getMeta('subtitle'));
        $this->assertSame('<p>Kajian terumbu karang</p>', $submission->getMeta('abstract'));
        $this->assertSame(['laut', 'karang'], $submission->getMeta('keywords'));

        Livewire::test(DetailStep::class, ['record' => $submission])
            ->assertSet('meta.keywords.id', ['laut', 'karang'])
            ->assertSet('meta.keywords.en', ['sea', 'coral', 'reef'])
            ->assertSet('meta.title.en', 'Coral reefs');
    }

    public function test_detail_form_puts_the_paper_language_first_and_saves_every_language(): void
    {
        $submission = $this->makePaper($this->author, 'id', [
            'title' => ['id' => 'Terumbu karang'],
            'abstract' => ['id' => '<p>Kajian terumbu karang</p>'],
        ], SubmissionStage::PeerReview, SubmissionStatus::OnReview);

        Livewire::test(Detail::class, ['submission' => $submission])
            ->assertSeeHtmlInOrder(['meta.title.id', 'meta.title.en'])
            ->assertSeeHtmlInOrder(['meta.abstract.id', 'meta.abstract.en'])
            ->set('meta.title.id', '')
            ->set('meta.title.en', 'Coral reefs')
            ->call('submit')
            ->assertHasErrors(['meta.title.id' => 'required'])
            ->assertHasNoErrors(['meta.title.en']);

        Livewire::test(Detail::class, ['submission' => $submission])
            ->set('meta.title.en', 'Coral reefs')
            ->set('meta.subtitle.id', 'Kajian lapangan')
            ->set('meta.keywords.id', ['laut', 'karang'])
            ->set('meta.keywords.en', ['sea'])
            ->set('meta.abstract.en', '<p>A study of reefs</p>')
            ->call('submit')
            ->assertHasNoErrors();

        $submission = Submission::query()->find($submission->getKey());

        $this->assertSame('Coral reefs', $submission->getStoredTranslation('title', 'en'));
        $this->assertSame(['sea'], $submission->getStoredTranslation('keywords', 'en'));
        $this->assertSame('<p>A study of reefs</p>', $submission->getStoredTranslation('abstract', 'en'));
        $this->assertSame('Terumbu karang', $submission->getMeta('title'));
        $this->assertSame('Kajian lapangan', $submission->getMeta('subtitle'));
        $this->assertSame(['laut', 'karang'], $submission->getMeta('keywords'));

        Livewire::test(Detail::class, ['submission' => $submission])
            ->assertSet('meta.keywords.id', ['laut', 'karang'])
            ->assertSet('meta.keywords.en', ['sea'])
            ->set('meta.keywords.en', [])
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertFalse(Translate::query()
            ->where('translatable_id', $submission->getKey())
            ->where('translatable_type', $submission->getMorphClass())
            ->where('key', 'keywords')
            ->where('locale', 'en')
            ->exists());
    }

    public function test_keyword_lists_round_trip_through_the_translation_layer(): void
    {
        $submission = $this->makePaper($this->author, 'en', []);

        $submission->setTranslation('keywords', 'id', [' laut ', '', 'karang, "biru"', 'ü']);

        $this->assertSame(['laut', 'karang, "biru"', 'ü'], Submission::query()->find($submission->getKey())->getStoredTranslation('keywords', 'id'));
        $this->assertSame(['laut', 'karang, "biru"', 'ü'], Submission::query()->find($submission->getKey())->translated('keywords', 'id'));

        // A legacy comma-separated value is read as a list.
        $submission->setMeta('keywords', 'sea, coral');
        $submission->flushCache();

        $this->assertSame(['sea', 'coral'], Submission::query()->find($submission->getKey())->translated('keywords', 'en'));

        $submission->setTranslation('keywords', 'id', []);

        $this->assertNull(Submission::query()->find($submission->getKey())->getStoredTranslation('keywords', 'id'));
    }

    public function test_models_without_a_declared_primary_language_keep_the_site_default(): void
    {
        $track = Track::query()->create(['title' => 'Chemistry', 'abbreviation' => 'CHEM']);
        $track->setTranslation('title', 'id', 'Kimia');

        $this->assertSame('en', $track->getPrimaryContentLocale());
        $this->assertSame(['en', 'id'], $track->getContentLocalesForForm());
        $this->assertSame('Chemistry', $track->translated('title', 'fr'));

        $this->enableLanguages(['en', 'id'], default: 'id');

        $track = Track::query()->find($track->getKey());

        $this->assertSame('id', $track->getPrimaryContentLocale());
        $this->assertSame('Kimia', $track->translated('title', 'fr'));
    }
}
