<?php

namespace Tests\Feature\Multilingual;

use App\Infolists\Infolist as AppInfolist;
use App\Models\Conference;
use App\Models\ScheduledConference;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\PaymentFormItemTable;
use App\Panel\ScheduledConference\Livewire\PresentationSetting;
use App\Panel\ScheduledConference\Livewire\ReviewSetupSetting;
use App\Panel\ScheduledConference\Livewire\SubmissionFormItemTable;
use App\Panel\ScheduledConference\Livewire\TrackTable;
use App\Panel\ScheduledConference\Pages\Payments;
use App\Panel\ScheduledConference\Pages\Presentations;
use App\Panel\ScheduledConference\Pages\ReviewResult;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\CreateSubmission;
use App\Panel\ScheduledConference\Widgets\Overview;
use App\Providers\PanelProvider;
use App\Managers\PaymentManager;
use Filament\Facades\Filament;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class InterfaceLabelTranslationTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);

        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));
        URL::defaults(['conference' => $this->conference->path, 'serie' => $this->scheduledConference->path]);
        Gate::before(fn () => true);

        $this->actingAs(User::query()->create([
            'given_name' => 'Manager',
            'email' => 'interface-labels@example.test',
            'password' => 'password123456',
        ]));

        app()->setLocale('fr');
    }

    public function test_workflow_settings_render_in_french(): void
    {
        Livewire::test(PresentationSetting::class)
            ->assertSee('Paramètres des présentations')
            ->assertSee('Date d’ouverture des présentations')
            ->assertSee('Date de clôture des présentations');

        Livewire::test(ReviewSetupSetting::class)
            ->assertSee('Évaluation ouverte pour l’auteur par défaut');

        Livewire::test(SubmissionFormItemTable::class)
            ->assertSee('Formulaire de soumission')
            ->assertSee('Nouvel élément');

        Livewire::test(PaymentFormItemTable::class, ['paymentType' => PaymentManager::TYPE_SUBMISSION_FEE])
            ->assertSee('Nouvel élément');

        Livewire::test(TrackTable::class)
            ->loadTable()
            ->assertSee('Éditeurs');
    }

    public function test_page_titles_and_navigation_use_the_catalog(): void
    {
        $this->assertSame('Présentations', Presentations::getNavigationLabel());
        $this->assertSame('Présentations', (new Presentations)->getTitle());
        $this->assertSame('Résultats des évaluations', ReviewResult::getNavigationLabel());
        $this->assertSame('Résultats des évaluations', (new ReviewResult)->getTitle());
        $this->assertSame('Faire une soumission', (new CreateSubmission)->getTitle());
    }

    public function test_payment_tabs_translate_while_their_ids_stay_stable(): void
    {
        $tabsFor = function (string $locale) {
            app()->setLocale($locale);
            $page = new Payments;

            return collect($page->infolist(Infolist::make($page))->getFlatComponents())
                ->filter(fn ($component) => $component instanceof Tab)
                ->values();
        };

        $english = $tabsFor('en');
        $french = $tabsFor('fr');

        $this->assertSame($english->map->getId()->all(), $french->map->getId()->all());
        foreach (['Général', 'Frais de soumission', 'Frais de participation', 'Facture', 'Mode de paiement'] as $label) {
            $this->assertContains($label, $french->map->getLabel()->all());
        }
    }

    public function test_dashboard_overview_labels_are_french(): void
    {
        $widget = new Overview;
        $components = collect($widget->scheduledConferenceInfolist(AppInfolist::make($widget))->getFlatComponents(true));

        $actions = $components
            ->filter(fn ($component) => $component instanceof Section)
            ->flatMap(fn (Section $section) => $section->getHeaderActions())
            ->mapWithKeys(fn ($action) => [$action->getName() => $action->getLabel()]);
        $this->assertSame('Paramètres', $actions['setting']);
        $this->assertSame('Publier', $actions['publish']);

        $entries = $components
            ->filter(fn ($component) => $component instanceof TextEntry)
            ->mapWithKeys(fn (TextEntry $component) => [$component->getName() => $component->getLabel()]);

        $this->assertSame('Dates de la conférence', $entries['full_date']);
        $this->assertSame('Soumises', $entries['submitted']);
        $this->assertSame('Soumissions en cours d’évaluation', $entries['reviews']);
    }
}
