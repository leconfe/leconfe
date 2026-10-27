<?php

namespace Tests\Feature\Multilingual;

use App\Actions\Permissions\PermissionPopulateAction;
use App\Livewire\LanguageSwitcher;
use App\Models\Timeline;
use App\Models\User;
use App\Panel\Administration\Livewire\LanguageSetting;
use App\Panel\Administration\Pages\Profile;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\CreateSubmission;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class FrenchLanguageControlsTest extends TestCase
{
    use InteractsWithContentLanguages;
    use MakesPapers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableLanguages(['fr', 'en'], 'fr');
    }

    public function test_language_block_is_labelled_in_french(): void
    {
        app()->getSite()->setMeta('sidebars', ['language']);

        $this->withoutVite()
            ->withSession(['locale' => 'fr'])
            ->get(route('livewirePageGroup.website.pages.home'))
            ->assertOk()
            ->assertSee('aria-label="Changer la langue de l&#039;interface"', false)
            ->assertSee('Langue actuelle')
            ->assertDontSee('Switch language')
            ->assertDontSee('Current language');
    }

    public function test_panel_language_switcher_is_headed_as_the_interface_language(): void
    {
        $this->actingAs($this->makeUser());
        App::setLocale('fr');

        Livewire::test(LanguageSwitcher::class)
            ->assertSee('Langue de l\'interface')
            ->assertSee('English');
    }

    public function test_language_settings_say_what_they_affect_in_english(): void
    {
        $this->actingAs($this->makeUser());
        $this->useConference($this->makeConference());
        App::setLocale('en');

        Livewire::test(LanguageSetting::class)
            ->assertSee('Content languages')
            ->assertSee('Content languages are the languages visitors can switch the interface to and that content can be translated into.');

        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_ADMINISTRATION));

        Livewire::test(Profile::class)
            ->assertSee('Interface and email language')
            ->assertSee('Choosing a language from the interface language menu also updates this setting.')
            ->assertDontSee('Emails to you');
    }

    public function test_profile_interface_and_email_language_is_in_french(): void
    {
        $this->actingAs($this->makeUser());
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_ADMINISTRATION));
        App::setLocale('fr');

        Livewire::test(Profile::class)
            ->assertSee('Langue de l\'interface et des e-mails')
            ->assertSee('Choisir une langue dans le menu de langue de l\'interface met aussi ce réglage à jour.')
            ->assertDontSee('vous sont adressés')
            ->assertDontSee('Preferred language')
            ->assertDontSee('Emails are sent to you in this language.');
    }

    public function test_language_settings_are_in_french(): void
    {
        $this->actingAs($this->makeUser());
        $this->useConference($this->makeConference());
        App::setLocale('fr');

        Livewire::test(LanguageSetting::class)
            ->assertSee('L’anglais est utilisé si aucune langue n’est cochée.')
            ->assertSee('Langues du contenu')
            ->assertSee('Les langues du contenu sont celles dans lesquelles les visiteurs peuvent afficher l\'interface et dans lesquelles le contenu peut être traduit.')
            ->assertDontSee('English is used when no language is selected.');
    }

    public function test_submission_paper_language_is_in_french(): void
    {
        $conference = $this->makeConference();
        $this->useConference($conference, $this->makeScheduledConference($conference));
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));
        Gate::before(fn () => true);
        PermissionPopulateAction::run();
        Timeline::query()->create([
            'name' => 'Submission open',
            'date' => now()->subDay(),
            'type' => Timeline::TYPE_SUBMISSION_OPEN,
        ]);
        $this->makeTrack();
        $this->actingAs($this->makeAuthor());
        App::setLocale('fr');

        Livewire::test(CreateSubmission::class)
            ->assertSee('Langue de l\'article')
            ->assertSee('La langue dans laquelle l\'article est rédigé.')
            ->assertDontSee('Paper language')
            ->assertDontSee('The language the paper is written in.');
    }

    protected function makeUser(): User
    {
        return User::query()->create([
            'given_name' => 'Claire',
            'family_name' => 'Martin',
            'email' => 'claire@example.test',
            'password' => 'password123456',
        ]);
    }
}
