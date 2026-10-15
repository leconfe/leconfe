<?php

namespace Tests\Feature\Multilingual;

use App\Models\Conference;
use App\Models\ScheduledConference;
use App\Models\User;
use App\Panel\Conference\Livewire\MastHeadSetting as ConferenceMastHeadSetting;
use App\Panel\ScheduledConference\Livewire\MastHeadSetting as ScheduledConferenceMastHeadSetting;
use App\Support\Multilingual\ContentLocales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

/**
 * Content languages are configured once for the site. Language settings stored on a
 * conference (its defaults, or values left by older versions) must not narrow them.
 */
class SiteWideLanguagesTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = $this->makeConference('Annual Conference', 'annual');
        $this->scheduledConference = $this->makeScheduledConference($this->conference, 'Annual Meeting 2026', '2026');

        $this->conference->setManyMeta([
            'settings_languages' => ['en'],
            'settings_default_language' => 'en',
        ]);

        $this->enableLanguages(['en', 'id', 'fr']);

        $this->useConference($this->conference);
    }

    public function test_conference_context_uses_the_site_languages(): void
    {
        $locales = app(ContentLocales::class);

        $this->assertSame(['en', 'id', 'fr'], $locales->enabled());
        $this->assertSame('en', $locales->default());
        $this->assertTrue($locales->isMultilingual());

        $this->enableLanguages(['en', 'id', 'fr'], 'id');

        $this->assertSame('id', $locales->default());
        $this->assertSame(['id', 'en', 'fr'], $locales->ordered());
    }

    public function test_masthead_forms_are_multilingual_with_site_languages_only(): void
    {
        $this->actingAs(User::query()->create([
            'given_name' => 'Site',
            'family_name' => 'Manager',
            'email' => 'manager@example.test',
            'password' => 'password123456',
        ]));

        Livewire::test(ConferenceMastHeadSetting::class)
            ->assertSeeHtml('wire:model="formData.name.id"')
            ->set('formData.name.en', 'Annual Conference')
            ->set('formData.name.id', 'Konferensi Tahunan')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasNoErrors();

        $this->useConference($this->conference, $this->scheduledConference);

        Livewire::test(ScheduledConferenceMastHeadSetting::class)
            ->assertSeeHtml('wire:model="formData.title.id"')
            ->set('formData.title.en', 'Annual Meeting 2026')
            ->set('formData.title.id', 'Pertemuan Tahunan 2026')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasNoErrors();

        $this->assertSame('Konferensi Tahunan', Conference::query()->find($this->conference->getKey())->translated('name', 'id'));
        $this->assertSame('Pertemuan Tahunan 2026', ScheduledConference::query()->find($this->scheduledConference->getKey())->translated('title', 'id'));
    }

    public function test_conference_frontend_switches_and_renders_site_languages(): void
    {
        $this->conference->setTranslation('name', 'id', 'Konferensi Tahunan');
        $home = route('livewirePageGroup.conference.pages.home', ['conference' => $this->conference->path]);

        $this->withHeader('Referer', $home)
            ->get(route('livewirePageGroup.conference.pages.set-locale', ['conference' => $this->conference->path, 'lang' => 'id']))
            ->assertRedirect($home)
            ->assertSessionHas('locale', 'id');

        $this->withoutVite()
            ->withSession(['locale' => 'id'])
            ->get($home)
            ->assertOk()
            ->assertSee('lang="id"', false)
            ->assertSee('data-language-switcher', false)
            ->assertSee('Konferensi Tahunan');
    }

    public function test_scheduled_conference_frontend_switches_and_renders_site_languages(): void
    {
        $this->useConference($this->conference, $this->scheduledConference);
        $this->scheduledConference->setTranslation('title', 'id', 'Pertemuan Tahunan 2026');
        $parameters = ['conference' => $this->conference->path, 'serie' => $this->scheduledConference->path];

        $this->get(route('livewirePageGroup.scheduledConference.pages.set-locale', [...$parameters, 'lang' => 'id']))
            ->assertSessionHas('locale', 'id');

        $this->withoutVite()
            ->withSession(['locale' => 'id'])
            ->get(route('livewirePageGroup.scheduledConference.pages.home', $parameters))
            ->assertOk()
            ->assertSee('lang="id"', false)
            ->assertSee('data-language-switcher', false)
            ->assertSee('Pertemuan Tahunan 2026');
    }

    public function test_fallback_and_default_sync_follow_the_site_default_language(): void
    {
        $this->enableLanguages(['en', 'id', 'fr'], 'id');
        $this->conference->setManyMeta([
            'settings_languages' => ['en', 'fr'],
            'settings_default_language' => 'fr',
        ]);

        $this->conference->setTranslation('name', 'id', 'Konferensi Tahunan');
        $this->conference->setTranslation('about', 'id', '<p>Tentang konferensi</p>');
        $this->conference->setTranslation('name', 'fr', 'Conférence annuelle');

        $fresh = Conference::query()->find($this->conference->getKey());

        $this->assertSame('Konferensi Tahunan', $fresh->name);
        $this->assertSame('<p>Tentang konferensi</p>', $fresh->getMeta('about'));
        $this->assertSame('Konferensi Tahunan', $fresh->translated('name', 'en'));
        $this->assertSame('Conférence annuelle', $fresh->translated('name', 'fr'));
        $this->assertSame('<p>Tentang konferensi</p>', $fresh->translated('about', 'fr'));
    }
}
