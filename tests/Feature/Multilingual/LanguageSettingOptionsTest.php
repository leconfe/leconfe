<?php

namespace Tests\Feature\Multilingual;

use App\Models\User;
use App\Panel\Administration\Livewire\LanguageSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class LanguageSettingOptionsTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::query()->create([
            'given_name' => 'Site',
            'family_name' => 'Manager',
            'email' => 'manager@example.test',
            'password' => 'password123456',
        ]));

        app()->setLocale('fr');
    }

    public function test_languages_are_named_in_their_own_language_with_the_english_name_below(): void
    {
        $this->enableLanguages(['en']);

        Livewire::test(LanguageSetting::class)
            ->assertSeeHtml('Français')
            ->assertSeeHtml('Deutsch')
            ->assertSeeHtml('日本語')
            ->assertSeeInOrder(['Français', 'French'])
            ->assertSeeInOrder(['Deutsch', 'German']);
    }

    public function test_saved_languages_are_listed_first_with_the_default_language_on_top(): void
    {
        $this->enableLanguages(['en', 'fr'], 'fr');

        Livewire::test(LanguageSetting::class)
            ->assertSeeHtmlInOrder(['value="fr"', 'value="en"', 'value="ar"', 'value="de"']);
    }

    public function test_search_field_has_a_short_placeholder(): void
    {
        $this->enableLanguages(['en']);

        Livewire::test(LanguageSetting::class)
            ->assertSeeHtml('placeholder="Rechercher"')
            ->assertDontSeeHtml('Commencez à taper pour rechercher');
    }
}
