<?php

namespace Tests\Feature\Multilingual;

use App\Classes\ImportExport\ExportArticleCrossref;
use App\Frontend\Conference\Pages\ProceedingDetail;
use App\Frontend\Conference\Pages\Proceedings;
use App\Models\Conference;
use App\Models\DOI;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Proceeding;
use App\Models\ScheduledConference;
use App\Models\Stakeholder;
use App\Models\StakeholderLevel;
use App\Models\Submission;
use App\Models\User;
use App\Panel\Administration\Livewire\SponsorLevelTable;
use App\Panel\Administration\Livewire\SponsorTable;
use App\Panel\Conference\Livewire\ProceedingDOI;
use App\Panel\Conference\Resources\ProceedingResource\Pages\ManageProceedings;
use App\Panel\Conference\Resources\ProceedingResource\Pages\ViewProceeding;
use App\Providers\PanelProvider;
use DOMDocument;
use DOMXPath;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use RahmanRamsi\LaravelTranslatable\Translate;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class SponsorLevelAndProceedingTranslationTest extends TestCase
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

        Gate::before(fn () => true);

        $this->author = $this->makeAuthor();
        $this->actingAs($this->author);
    }

    public function test_sponsor_level_form_creates_and_updates_every_language(): void
    {
        $this->usePanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE);
        $this->enableLanguages(['en', 'id']);

        Livewire::test(SponsorLevelTable::class)
            ->loadTable()
            ->mountTableAction('create')
            ->assertSeeHtml('mountedTableActionsData.0.name.id')
            ->assertSeeHtml('mountedTableActionsData.0.description.id')
            ->setTableActionData(['name' => ['en' => '', 'id' => 'Emas']])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['name.en' => 'required'])
            ->assertHasNoTableActionErrors(['name.id'])
            ->setTableActionData([
                'name' => ['en' => 'Gold', 'id' => 'Emas'],
                'description' => ['en' => 'Main sponsors', 'id' => 'Sponsor utama'],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $level = StakeholderLevel::query()->sole();

        $this->assertSame(StakeholderLevel::TYPE_SPONSOR, $level->type);
        $this->assertSame('Gold', $level->name);
        $this->assertSame('Main sponsors', $level->description);
        $this->assertSame('Emas', $level->translated('name', 'id'));
        $this->assertSame('Sponsor utama', $level->translated('description', 'id'));

        Livewire::test(SponsorLevelTable::class)
            ->loadTable()
            ->mountTableAction('edit', $level)
            ->assertTableActionDataSet([
                'name' => ['en' => 'Gold', 'id' => 'Emas'],
                'description' => ['en' => 'Main sponsors', 'id' => 'Sponsor utama'],
            ])
            ->setTableActionData(['name' => ['en' => 'Gold partner', 'id' => 'Mitra emas']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $level = StakeholderLevel::query()->find($level->getKey());

        $this->assertSame('Gold partner', $level->name);
        $this->assertSame('Mitra emas', $level->translated('name', 'id'));
        $this->assertSame('Sponsor utama', $level->translated('description', 'id'));
    }

    public function test_sponsor_level_form_keeps_single_language_behaviour(): void
    {
        $this->usePanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE);

        Livewire::test(SponsorLevelTable::class)
            ->loadTable()
            ->callTableAction('create', data: ['name' => 'Gold', 'description' => 'Main sponsors'])
            ->assertHasNoTableActionErrors();

        $level = StakeholderLevel::query()->sole();

        $this->assertSame('Main sponsors', $level->description);
        $this->assertNoTranslationRows($level);
    }

    public function test_sponsor_level_panel_tables_show_the_current_language(): void
    {
        $this->usePanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE);
        $this->enableLanguages(['en', 'id', 'fr']);

        $level = $this->makeSponsorLevel();
        $sponsor = Stakeholder::query()->create([
            'type' => Stakeholder::TYPE_SPONSOR,
            'level_id' => $level->getKey(),
            'name' => 'Acme',
        ]);

        app()->setLocale('id');

        Livewire::test(SponsorLevelTable::class)
            ->loadTable()
            ->assertSee('Emas')
            ->assertSee('Sponsor utama')
            ->assertDontSee('Main sponsors');

        Livewire::test(SponsorTable::class)
            ->loadTable()
            ->assertSee('Emas')
            ->mountTableAction('edit', $sponsor)
            ->assertSee('Emas');

        app()->setLocale('fr');

        Livewire::test(SponsorLevelTable::class)
            ->loadTable()
            ->assertSee('Gold')
            ->assertSee('Main sponsors')
            ->assertDontSee('Emas');
    }

    public function test_sponsor_level_renders_the_session_language_on_the_home_page(): void
    {
        $this->enableLanguages(['en', 'id', 'fr']);

        $level = $this->makeSponsorLevel();
        $sponsor = Stakeholder::query()->create([
            'type' => Stakeholder::TYPE_SPONSOR,
            'level_id' => $level->getKey(),
            'name' => 'Acme',
        ]);
        $sponsor->addMedia(UploadedFile::fake()->image('logo.png'))->toMediaCollection('logo');

        $url = route('livewirePageGroup.scheduledConference.pages.home', [
            'conference' => $this->conference->path,
            'serie' => $this->scheduledConference->path,
        ]);

        $this->frontend('id', $url)
            ->assertSee('Emas')
            ->assertDontSee('>Gold<', false);

        $this->frontend('fr', $url)
            ->assertSee('Gold')
            ->assertDontSee('Emas');
    }

    public function test_proceeding_form_creates_and_updates_every_language(): void
    {
        $this->usePanel(PanelProvider::PANEL_CONFERENCE);
        $this->enableLanguages(['en', 'id']);

        Livewire::test(ManageProceedings::class)
            ->mountAction('create')
            ->assertSeeHtml('mountedActionsData.0.title.id')
            ->assertSeeHtml('mountedActionsData.0.description.id')
            ->setActionData(['title' => ['en' => '', 'id' => 'Prosiding 2026']])
            ->callMountedAction()
            ->assertHasActionErrors(['title.en' => 'required'])
            ->assertHasNoActionErrors(['title.id'])
            ->setActionData([
                'volume' => 1,
                'year' => 2026,
                'title' => ['en' => 'Proceedings 2026', 'id' => 'Prosiding 2026'],
                'description' => ['en' => '<p>Selected papers</p>', 'id' => '<p>Makalah terpilih</p>'],
                'meta' => ['additional_content' => [['title' => 'Editorial board', 'content' => '<p>Board</p>']]],
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $proceeding = Proceeding::query()->sole();

        $this->assertSame('Proceedings 2026', $proceeding->title);
        $this->assertSame('<p>Selected papers</p>', $proceeding->description);
        $this->assertSame(1, (int) $proceeding->volume);
        $this->assertSame('Prosiding 2026', $proceeding->translated('title', 'id'));
        $this->assertSame('<p>Makalah terpilih</p>', $proceeding->translated('description', 'id'));
        $this->assertSame('Editorial board', $proceeding->getMeta('additional_content')[0]['title']);

        Livewire::test(ManageProceedings::class)
            ->mountTableAction('edit', $proceeding)
            ->assertTableActionDataSet([
                'title' => ['en' => 'Proceedings 2026', 'id' => 'Prosiding 2026'],
                'description' => ['en' => '<p>Selected papers</p>', 'id' => '<p>Makalah terpilih</p>'],
            ])
            ->setTableActionData(['title' => ['en' => 'Annual proceedings', 'id' => 'Prosiding tahunan']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $proceeding = Proceeding::query()->find($proceeding->getKey());

        $this->assertSame('Annual proceedings', $proceeding->title);
        $this->assertSame('Prosiding tahunan', $proceeding->translated('title', 'id'));
        $this->assertSame('<p>Makalah terpilih</p>', $proceeding->translated('description', 'id'));
        $this->assertSame('Editorial board', $proceeding->getMeta('additional_content')[0]['title']);

        Livewire::test(ViewProceeding::class, ['record' => $proceeding->getKey()])
            ->assertSet('data.title', ['en' => 'Annual proceedings', 'id' => 'Prosiding tahunan'])
            ->set('data.description.id', '<p>Makalah pilihan</p>')
            ->call('submit')
            ->assertHasNoErrors();

        $proceeding = Proceeding::query()->find($proceeding->getKey());

        $this->assertSame('Annual proceedings', $proceeding->title);
        $this->assertSame('<p>Makalah pilihan</p>', $proceeding->translated('description', 'id'));
    }

    public function test_proceeding_form_keeps_single_language_behaviour(): void
    {
        $this->usePanel(PanelProvider::PANEL_CONFERENCE);

        Livewire::test(ManageProceedings::class)
            ->callAction('create', data: ['title' => 'Proceedings 2026', 'description' => '<p>Selected papers</p>'])
            ->assertHasNoActionErrors();

        $proceeding = Proceeding::query()->sole();

        $this->assertSame('<p>Selected papers</p>', $proceeding->description);
        $this->assertNoTranslationRows($proceeding);
    }

    public function test_proceeding_panel_pages_show_the_current_language(): void
    {
        $this->usePanel(PanelProvider::PANEL_CONFERENCE);
        $this->enableLanguages(['en', 'id', 'fr']);

        $proceeding = $this->makeTranslatedProceeding();

        app()->setLocale('id');

        Livewire::test(ManageProceedings::class)
            ->set('activeTab', 'back')
            ->assertSee('Prosiding 2026')
            ->assertDontSee('Proceedings 2026');

        Livewire::test(ProceedingDOI::class)
            ->assertSee('Prosiding 2026');

        $this->assertSame('Prosiding 2026', Livewire::test(ViewProceeding::class, ['record' => $proceeding->getKey()])->instance()->getTitle());

        app()->setLocale('fr');

        Livewire::test(ManageProceedings::class)
            ->set('activeTab', 'back')
            ->assertSee('Proceedings 2026')
            ->assertDontSee('Prosiding 2026');
    }

    public function test_proceeding_renders_the_session_language_on_the_frontend(): void
    {
        $this->enableLanguages(['en', 'id', 'fr']);

        $proceeding = $this->makeTranslatedProceeding();
        $paper = $this->makePaper($this->author, 'en', [
            'title' => ['en' => 'Coral reefs'],
        ], SubmissionStage::Proceeding, SubmissionStatus::Published, $proceeding);

        $listUrl = route(Proceedings::getRouteName('conference'), ['conference' => $this->conference->path]);
        $detailUrl = route(ProceedingDetail::getRouteName('conference'), [
            'conference' => $this->conference->path,
            'proceeding' => $proceeding->getKey(),
        ]);

        $this->frontend('id', $listUrl)
            ->assertSee('Prosiding 2026')
            ->assertDontSee('Proceedings 2026');

        $this->frontend('id', $detailUrl)
            ->assertSee('Vol. 1 No. 2 (2026): Prosiding 2026')
            ->assertSee('Makalah terpilih')
            ->assertDontSee('Selected papers');

        $this->visitPaper($this->conference, $paper, 'id')
            ->assertSee('Vol. 1 No. 2 (2026): Prosiding 2026');

        $this->frontend('id', route('livewirePageGroup.website.pages.proceedings'))
            ->assertSee('Prosiding 2026');

        $this->frontend('fr', $detailUrl)
            ->assertSee('Vol. 1 No. 2 (2026): Proceedings 2026')
            ->assertSee('Selected papers')
            ->assertDontSee('Prosiding 2026');
    }

    public function test_crossref_export_keeps_the_primary_proceeding_title(): void
    {
        $this->enableLanguages(['en', 'id']);

        $paper = $this->makePaper($this->author, 'en', [
            'title' => ['en' => 'Coral reefs'],
        ], SubmissionStage::Proceeding, SubmissionStatus::Published, $this->makeTranslatedProceeding());

        app()->getSite()->setMeta('publisher_name', 'Leconfe Press');
        $this->conference->setManyMeta([
            'doi_crossref_depositor_name' => 'Depositor',
            'doi_crossref_depositor_email' => 'depositor@example.test',
        ]);

        DOI::query()->forceCreate([
            'conference_id' => $this->conference->getKey(),
            'doiable_type' => $paper->getMorphClass(),
            'doiable_id' => $paper->getKey(),
            'doi' => '10.1234/leconfe.'.$paper->getKey(),
        ]);

        app()->setLocale('id');

        $paper = Submission::query()->with(['doi', 'authors', 'translate'])->find($paper->getKey());

        $document = new DOMDocument;
        $this->assertTrue($document->loadXML((new ExportArticleCrossref($paper))->exportXml()));

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('cr', 'http://www.crossref.org/schema/5.3.0');

        $this->assertSame('Proceedings 2026', $xpath->evaluate('string(//cr:proceedings_metadata/cr:proceedings_title)'));
    }

    protected function makeSponsorLevel(): StakeholderLevel
    {
        $level = StakeholderLevel::query()->create([
            'type' => StakeholderLevel::TYPE_SPONSOR,
            'name' => 'Gold',
            'description' => 'Main sponsors',
        ]);
        $level->setTranslation('name', 'id', 'Emas');
        $level->setTranslation('description', 'id', 'Sponsor utama');

        return $level;
    }

    protected function makeTranslatedProceeding(): Proceeding
    {
        $proceeding = $this->makeProceeding();
        $proceeding->update(['description' => '<p>Selected papers</p>']);
        $proceeding->setTranslation('title', 'id', 'Prosiding 2026');
        $proceeding->setTranslation('description', 'id', '<p>Makalah terpilih</p>');

        return $proceeding;
    }

    protected function usePanel(string $panel): void
    {
        Filament::setCurrentPanel(Filament::getPanel($panel));
    }

    protected function frontend(string $locale, string $url)
    {
        return $this->withoutVite()
            ->withSession(['locale' => $locale])
            ->get($url)
            ->assertOk();
    }

    protected function assertNoTranslationRows(Model $model): void
    {
        $this->assertFalse(Translate::query()
            ->where('translatable_type', $model->getMorphClass())
            ->where('translatable_id', $model->getKey())
            ->exists());
    }
}
