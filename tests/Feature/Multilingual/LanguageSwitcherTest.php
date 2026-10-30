<?php

namespace Tests\Feature\Multilingual;

use App\Livewire\LanguageSwitcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class LanguageSwitcherTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->getSite();
        $this->enableLanguages(['en', 'id']);
    }

    public function test_switch_route_stores_an_enabled_locale_and_returns_to_a_same_host_referer(): void
    {
        $referer = url('/about-system?tab=1');

        $this->withHeader('Referer', $referer)
            ->get(route('livewirePageGroup.website.pages.set-locale', ['lang' => 'id']))
            ->assertRedirect($referer)
            ->assertSessionHas('locale', 'id');
    }

    public function test_unknown_or_disabled_locales_do_not_change_the_session(): void
    {
        foreach (['xx', 'fr', 'kab'] as $locale) {
            $this->withSession(['locale' => 'en'])
                ->withHeader('Referer', url('/'))
                ->get(route('livewirePageGroup.website.pages.set-locale', ['lang' => $locale]))
                ->assertRedirect(url('/'))
                ->assertSessionHas('locale', 'en');
        }

        $this->flushSession();

        $this->get(route('livewirePageGroup.website.pages.set-locale', ['lang' => 'fr']))
            ->assertSessionMissing('locale');
    }

    public function test_switch_route_never_redirects_to_a_foreign_host(): void
    {
        $home = route('livewirePageGroup.website.pages.home');

        foreach ([
            'https://evil.example/phishing',
            '//evil.example/phishing',
            'http://evil.example\\@'.parse_url(url('/'), PHP_URL_HOST).'/',
            'http://user@evil.example/',
            'javascript:alert(1)',
            'not a url',
        ] as $referer) {
            $this->withHeader('Referer', $referer)
                ->get(route('livewirePageGroup.website.pages.set-locale', ['lang' => 'id']))
                ->assertRedirect($home)
                ->assertSessionHas('locale', 'id');
        }
    }

    public function test_conference_switch_route_falls_back_to_the_conference_home(): void
    {
        $conference = $this->makeConference();
        $this->useConference($conference);
        $this->enableLanguages(['en', 'id']);

        $this->withHeader('Referer', 'https://evil.example/')
            ->get(route('livewirePageGroup.conference.pages.set-locale', ['conference' => $conference->path, 'lang' => 'id']))
            ->assertRedirect($conference->getHomeUrl())
            ->assertSessionHas('locale', 'id');
    }

    public function test_panel_language_switcher_never_redirects_to_a_foreign_host(): void
    {
        request()->headers->set('referer', 'https://evil.example/phishing');

        $response = (new LanguageSwitcher)->switchLanguage('id');

        $this->assertStringNotContainsString('evil.example', $response->getTargetUrl());
        $this->assertSame(parse_url(url('/'), PHP_URL_HOST), parse_url($response->getTargetUrl(), PHP_URL_HOST));
        $this->assertSame('id', session('locale'));

        $referer = url('/administration/settings');
        request()->headers->set('referer', $referer);

        $this->assertSame($referer, (new LanguageSwitcher)->switchLanguage('en')->getTargetUrl());
        $this->assertSame('en', session('locale'));

        Livewire::test(LanguageSwitcher::class)
            ->assertSee('Indonesia')
            ->assertDontSee('Indonesian')
            ->call('switchLanguage', 'id')
            ->assertRedirect();
    }

    public function test_html_lang_and_dir_follow_the_active_locale(): void
    {
        $this->withoutVite()
            ->withSession(['locale' => 'id'])
            ->get(route('livewirePageGroup.website.pages.home'))
            ->assertOk()
            ->assertSee('lang="id"', false)
            ->assertSee('dir="ltr"', false);

        $this->enableLanguages(['en', 'ar']);

        $this->withoutVite()
            ->withSession(['locale' => 'ar'])
            ->get(route('livewirePageGroup.website.pages.home'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false);
    }
}
