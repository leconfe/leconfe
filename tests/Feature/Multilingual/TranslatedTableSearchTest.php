<?php

namespace Tests\Feature\Multilingual;

use App\Models\Conference;
use App\Models\Proceeding;
use App\Models\ScheduledConference;
use App\Models\User;
use App\Panel\Conference\Resources\ProceedingResource\Pages\ManageProceedings;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class TranslatedTableSearchTest extends TestCase
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

        $this->enableLanguages(['en', 'fr']);
        app()->setLocale('fr');
    }

    public function test_proceedings_are_found_by_their_translated_title(): void
    {
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_CONFERENCE));

        $proceeding = $this->makeFrenchProceeding('Ocean science proceedings', 'Biodiversité maritime');

        $otherConference = $this->makeConference('Other Conference', 'other');
        $this->useConference($otherConference);
        $otherProceeding = $this->makeFrenchProceeding('Coastal proceedings', 'Biodiversité côtière');
        $this->useConference($this->conference, $this->scheduledConference);
        app()->scopeCurrentConference();

        Livewire::test(ManageProceedings::class)
            ->set('activeTab', 'back')
            ->assertSee('Biodiversité maritime')
            ->searchTable('Biodiversité')
            ->assertCanSeeTableRecords([$proceeding])
            ->assertCanNotSeeTableRecords([$otherProceeding])
            ->searchTable('biodiversité')
            ->assertCanSeeTableRecords([$proceeding])
            ->searchTable('Ocean')
            ->assertCanSeeTableRecords([$proceeding])
            ->searchTable('Coastal')
            ->assertCanNotSeeTableRecords([$proceeding, $otherProceeding]);
    }

    protected function makeFrenchProceeding(string $title, string $frenchTitle): Proceeding
    {
        $proceeding = $this->makeProceeding($title);
        $proceeding->setTranslation('title', 'fr', $frenchTitle);

        return $proceeding;
    }
}
