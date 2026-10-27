<?php

namespace Tests\Feature\Multilingual;

use App\Models\User;
use App\Panel\Administration\Pages\Profile;
use App\Panel\Conference\Livewire\MastHeadSetting;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class LanguageNamesTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->getSite();
        $this->enableLanguages(['en', 'fr']);
    }

    protected function actAsManager(): void
    {
        $this->actingAs(User::query()->create([
            'given_name' => 'Site',
            'family_name' => 'Manager',
            'email' => 'manager@example.test',
            'password' => 'password123456',
        ]));
    }

    public function test_multilingual_field_labels_name_languages_in_the_interface_language(): void
    {
        $this->actAsManager();
        $conference = $this->makeConference();
        $this->useConference($conference);

        App::setLocale('fr');

        Livewire::test(MastHeadSetting::class)
            ->assertSee('(Anglais)')
            ->assertSee('(Français)')
            ->assertDontSee('(English)')
            ->assertDontSee('(French)');

        App::setLocale('en');

        Livewire::test(MastHeadSetting::class)
            ->assertSee('Name (English)')
            ->assertSee('Name (French)');
    }

    public function test_language_select_names_languages_in_the_interface_language(): void
    {
        $this->actAsManager();
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_ADMINISTRATION));

        App::setLocale('fr');

        $this->assertOptionLabels(Livewire::test(Profile::class)->html(), ['en' => 'Anglais', 'fr' => 'Français']);

        App::setLocale('en');

        $this->assertOptionLabels(Livewire::test(Profile::class)->html(), ['en' => 'English', 'fr' => 'French']);
    }

    public function test_language_switcher_announces_the_current_language_in_the_interface_language(): void
    {
        $this->makeConference();

        $this->withoutVite()
            ->withSession(['locale' => 'fr'])
            ->get(route('livewirePageGroup.website.pages.home'))
            ->assertOk()
            ->assertSee('<span class="sr-only">Langue actuelle :</span>', false)
            ->assertSee('<span lang="fr">Français</span>', false)
            ->assertSee('<span>English</span>', false)
            ->assertDontSee('<span lang="fr">French</span>', false);

        $this->withoutVite()
            ->withSession(['locale' => 'en'])
            ->get(route('livewirePageGroup.website.pages.home'))
            ->assertOk()
            ->assertSee('<span class="sr-only">Current language:</span>', false)
            ->assertSee('<span lang="en">English</span>', false)
            ->assertSee('<span>Français</span>', false);
    }

    /**
     * @param  array<string, string>  $labels
     */
    protected function assertOptionLabels(string $html, array $labels): void
    {
        foreach ($labels as $value => $label) {
            $this->assertMatchesRegularExpression('/<option\s+value="'.$value.'"\s*>\s*(<!--.*?-->\s*)*'.$label.'\s*(<!--.*?-->\s*)*<\/option>/u', $html);
        }
    }
}
