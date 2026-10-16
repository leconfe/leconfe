<?php

namespace Tests\Feature\Multilingual;

use App\Frontend\ScheduledConference\Pages\About;
use App\Models\Conference;
use App\Models\ScheduledConference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class FrontendRenderingTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected function setUp(): void
    {
        parent::setUp();

        $site = app()->getSite();
        $site->setMeta('name', 'Conference Portal');
        $site->setTranslation('name', 'id', 'Portal Konferensi');

        $this->conference = $this->makeConference('Annual Conference', 'annual');
        $this->conference->setTranslations('name', ['en' => 'Annual Conference', 'id' => 'Konferensi Tahunan']);
        $this->conference->setTranslations('about', ['en' => '<p>About the conference</p>', 'id' => '<p>Tentang konferensi</p>']);

        $this->scheduledConference = $this->makeScheduledConference($this->conference, 'Annual Meeting 2026', '2026');
        $this->scheduledConference->setTranslations('title', ['en' => 'Annual Meeting 2026', 'id' => 'Pertemuan Tahunan 2026']);
        $this->scheduledConference->setTranslations('summary', ['en' => '<p>Meeting summary</p>', 'id' => '<p>Ringkasan pertemuan</p>']);
        $this->scheduledConference->setTranslations('about', ['en' => '<p>About the meeting</p>', 'id' => '<p>Tentang pertemuan</p>']);

        $this->enableLanguages(['en', 'id', 'fr']);
    }

    public function test_site_home_renders_translated_content_for_the_session_locale(): void
    {
        $this->withoutVite()
            ->withSession(['locale' => 'id'])
            ->get(route('livewirePageGroup.website.pages.home'))
            ->assertOk()
            ->assertSee('<html', false)
            ->assertSee('lang="id"', false)
            ->assertSee('Portal Konferensi')
            ->assertSee('Pertemuan Tahunan 2026')
            ->assertSee('Ringkasan pertemuan')
            ->assertDontSee('Meeting summary');
    }

    public function test_site_home_falls_back_to_the_default_language(): void
    {
        $this->withoutVite()
            ->withSession(['locale' => 'fr'])
            ->get(route('livewirePageGroup.website.pages.home'))
            ->assertOk()
            ->assertSee('lang="fr"', false)
            ->assertSee('Conference Portal')
            ->assertSee('Annual Meeting 2026')
            ->assertSee('Meeting summary')
            ->assertDontSee('Pertemuan Tahunan 2026');
    }

    public function test_conference_home_renders_translated_content(): void
    {
        $this->useConference($this->conference);

        $this->withoutVite()
            ->withSession(['locale' => 'id'])
            ->get(route('livewirePageGroup.conference.pages.home', ['conference' => $this->conference->path]))
            ->assertOk()
            ->assertSee('<title>', false)
            ->assertSee('Konferensi Tahunan')
            ->assertSee('Tentang konferensi', false)
            ->assertSee('Pertemuan Tahunan 2026')
            ->assertSee('Ringkasan pertemuan')
            ->assertDontSee('About the conference');

        $this->withoutVite()
            ->withSession(['locale' => 'fr'])
            ->get(route('livewirePageGroup.conference.pages.home', ['conference' => $this->conference->path]))
            ->assertOk()
            ->assertSee('Annual Conference')
            ->assertSee('About the conference')
            ->assertSee('Annual Meeting 2026')
            ->assertDontSee('Konferensi Tahunan');
    }

    public function test_scheduled_conference_home_and_about_render_translated_content(): void
    {
        $this->useConference($this->conference, $this->scheduledConference);

        $parameters = ['conference' => $this->conference->path, 'serie' => $this->scheduledConference->path];

        $this->withoutVite()
            ->withSession(['locale' => 'id'])
            ->get(route('livewirePageGroup.scheduledConference.pages.home', $parameters))
            ->assertOk()
            ->assertSee('- Pertemuan Tahunan 2026</title>', false)
            ->assertSee('Tentang pertemuan', false)
            ->assertDontSee('About the meeting');

        $this->withoutVite()
            ->withSession(['locale' => 'id'])
            ->get(route(About::getRouteName('scheduledConference'), $parameters))
            ->assertOk()
            ->assertSee('Pertemuan Tahunan 2026')
            ->assertSee('Tentang pertemuan', false);

        $this->withoutVite()
            ->withSession(['locale' => 'fr'])
            ->get(route(About::getRouteName('scheduledConference'), $parameters))
            ->assertOk()
            ->assertSee('lang="fr"', false)
            ->assertSee('Annual Meeting 2026')
            ->assertSee('About the meeting', false)
            ->assertDontSee('Tentang pertemuan');
    }
}
