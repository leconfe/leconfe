<?php

namespace Tests\Feature\Multilingual;

use App\Models\User;
use App\Panel\Administration\Livewire\LanguageSetting;
use App\Panel\Administration\Livewire\SidebarSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class LanguageSidebarTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableLanguages(['fr', 'en'], 'fr');
    }

    public function test_header_renders_no_language_switcher(): void
    {
        $this->home('fr')
            ->assertDontSee(route('livewirePageGroup.website.pages.set-locale', ['lang' => 'en']), false)
            ->assertDontSee('Langue actuelle')
            ->assertDontSee('id="sidebar-language"', false);
    }

    public function test_block_lists_each_language_by_its_own_name_and_marks_the_current_one(): void
    {
        $this->activateBlock();

        $html = $this->home('fr')->assertSee('id="sidebar-language"', false)->getContent();
        $block = $this->block($html);

        $this->assertStringContainsString('<h2 class="text-heading">Langue</h2>', $block);
        $this->assertStringContainsString('<span class="sr-only">Langue actuelle :</span>', $block);
        $this->assertStringContainsString('<span lang="fr">Français</span>', $block);
        $this->assertStringContainsString('<span lang="en">English</span>', $block);
        $this->assertSame(1, substr_count($block, 'aria-current="true"'));
        $this->assertMatchesRegularExpression('/hreflang="fr"\s+aria-current="true"/', $block);

        $block = $this->block($this->home('en')->getContent());

        $this->assertStringContainsString('<span class="sr-only">Current language:</span>', $block);
        $this->assertMatchesRegularExpression('/hreflang="en"\s+aria-current="true"/', $block);
        $this->assertStringContainsString('<span lang="fr">Français</span>', $block);
    }

    public function test_block_renders_nothing_with_one_language(): void
    {
        $this->activateBlock();
        $this->enableLanguages(['fr'], 'fr');

        $this->home('fr')
            ->assertDontSee('id="sidebar-language"', false)
            ->assertDontSee('Langue actuelle');
    }

    public function test_switching_through_the_block_changes_the_locale_and_remembers_the_user_preference(): void
    {
        $user = User::query()->create([
            'given_name' => 'Test',
            'family_name' => 'User',
            'email' => 'user@example.com',
            'password' => 'password123456',
        ]);
        $this->actingAs($user);
        $this->activateBlock();

        preg_match('/href="([^"]+)"\s+hreflang="en"/', $this->block($this->home('fr')->getContent()), $match);

        $this->withHeader('Referer', url('/'))
            ->get(html_entity_decode($match[1]))
            ->assertRedirect(url('/'))
            ->assertSessionHas('locale', 'en');

        $this->assertSame('en', $user->refresh()->locale);
        $this->assertStringContainsString('<span class="sr-only">Current language:</span>', $this->block($this->get(url('/'))->getContent()));
    }

    public function test_scheduled_conference_block_switches_through_its_own_route(): void
    {
        $conference = $this->makeConference();
        $scheduledConference = $this->makeScheduledConference($conference);
        $this->useConference($conference, $scheduledConference);
        $scheduledConference->setMeta('sidebars', ['language']);

        $html = $this->withoutVite()
            ->withSession(['locale' => 'fr'])
            ->get(route('livewirePageGroup.scheduledConference.pages.home', [
                'conference' => $conference->path,
                'serie' => $scheduledConference->path,
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'href="'.route('livewirePageGroup.scheduledConference.pages.set-locale', [
                'conference' => $conference->path,
                'serie' => $scheduledConference->path,
                'lang' => 'en',
            ]).'"',
            $this->block($html),
        );
    }

    public function test_block_is_offered_in_the_sidebar_settings_at_every_level(): void
    {
        App::setLocale('fr');

        Livewire::test(SidebarSetting::class)->assertSee('Langue');

        $conference = $this->makeConference();
        $this->useConference($conference);

        Livewire::test(SidebarSetting::class)->assertSee('Langue');

        $this->useConference($conference, $this->makeScheduledConference($conference));

        Livewire::test(SidebarSetting::class)->assertSee('Langue');
    }

    public function test_language_settings_point_to_the_sidebar_block(): void
    {
        App::setLocale('fr');

        Livewire::test(LanguageSetting::class)
            ->assertSee('Les visiteurs changent de langue depuis le bloc Langue, à ajouter dans l&#039;onglet Barre latérale.', false);

        App::setLocale('en');

        Livewire::test(LanguageSetting::class)
            ->assertSee('Visitors switch language from the Language block, which you add in the Sidebar tab.');
    }

    protected function activateBlock(): void
    {
        app()->getSite()->setMeta('sidebars', ['language']);
    }

    protected function home(string $locale): TestResponse
    {
        return $this->withoutVite()
            ->withSession(['locale' => $locale])
            ->get(route('livewirePageGroup.website.pages.home'))
            ->assertOk();
    }

    protected function block(string $html): string
    {
        $this->assertSame(1, preg_match('/<div\s+id="sidebar-language"\s+class="sidebar sidebar-language[^"]*".*?<\/nav>/s', $html, $match));

        return $match[0];
    }
}
