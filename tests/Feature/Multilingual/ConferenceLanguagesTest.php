<?php

namespace Tests\Feature\Multilingual;

use App\Models\Conference;
use App\Models\ScheduledConference;
use App\Models\User;
use App\Panel\Administration\Livewire\LanguageSetting;
use App\Panel\Conference\Livewire\MastHeadSetting as ConferenceMastHeadSetting;
use App\Support\Multilingual\ContentLocales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class ConferenceLanguagesTest extends TestCase
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

        $this->enableLanguages(['en', 'id']);

        $this->actingAs(User::query()->create([
            'given_name' => 'Site',
            'family_name' => 'Manager',
            'email' => 'manager@example.test',
            'password' => 'password123456',
        ]));
    }

    public function test_conference_without_language_settings_uses_the_site_languages(): void
    {
        $this->useConference($this->conference);

        $locales = app(ContentLocales::class);

        $this->assertSame(['en', 'id'], $locales->enabled());
        $this->assertSame('en', $locales->default());
    }

    public function test_conference_languages_replace_the_site_languages(): void
    {
        $this->conference->setManyMeta([
            'settings_languages' => ['en', 'fr'],
            'settings_default_language' => 'fr',
        ]);

        $this->useConference($this->conference, $this->scheduledConference);

        $locales = app(ContentLocales::class);

        $this->assertSame(['en', 'fr'], $locales->enabled());
        $this->assertSame('fr', $locales->default());
        $this->assertSame(['fr', 'en'], $locales->ordered());
        $this->assertFalse($locales->isEnabled('id'));
    }

    public function test_conference_languages_do_not_change_the_site_or_other_conferences(): void
    {
        $other = $this->makeConference('Other Conference', 'other');

        $this->conference->setManyMeta([
            'settings_languages' => ['en'],
            'settings_default_language' => 'en',
        ]);

        $this->assertSame(['en', 'id'], app(ContentLocales::class)->enabled());

        $this->useConference($other);

        $this->assertSame(['en', 'id'], app(ContentLocales::class)->enabled());
    }

    public function test_conference_language_setting_saves_on_the_conference(): void
    {
        $this->useConference($this->conference);

        Livewire::test(LanguageSetting::class)
            ->assertSet('formData.languages', ['en', 'id'])
            ->assertSet('formData.default_language', 'en')
            ->set('formData.languages', ['en', 'fr'])
            ->set('formData.default_language', 'fr')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasNoErrors();

        $conference = Conference::query()->find($this->conference->getKey());

        $this->assertSame(['en', 'fr'], $conference->getMeta('settings_languages'));
        $this->assertSame('fr', $conference->getMeta('settings_default_language'));
        $this->assertSame(['en', 'id'], app()->getSite()->getMeta('settings_languages'));
        $this->assertSame(['en', 'fr'], app(ContentLocales::class)->enabled());
    }

    public function test_saving_without_languages_uses_english(): void
    {
        $this->useConference($this->conference);

        Livewire::test(LanguageSetting::class)
            ->set('formData.languages', [])
            ->assertSet('formData.default_language', 'en')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasNoErrors();

        $conference = Conference::query()->find($this->conference->getKey());

        $this->assertSame(['en'], $conference->getMeta('settings_languages'));
        $this->assertSame('en', $conference->getMeta('settings_default_language'));
        $this->assertSame(['en'], app(ContentLocales::class)->enabled());
        $this->assertFalse(app(ContentLocales::class)->isMultilingual());
    }

    public function test_unchecking_the_default_language_moves_the_default_to_a_checked_language(): void
    {
        Livewire::test(LanguageSetting::class)
            ->set('formData.default_language', 'id')
            ->set('formData.languages', ['en'])
            ->assertSet('formData.default_language', 'en');
    }

    public function test_empty_language_settings_fall_back_to_english(): void
    {
        $this->enableLanguages([], 'id');

        $locales = app(ContentLocales::class);

        $this->assertSame(['en'], $locales->enabled());
        $this->assertSame('en', $locales->default());
    }

    public function test_masthead_form_follows_the_conference_languages(): void
    {
        $this->conference->setManyMeta([
            'settings_languages' => ['en', 'fr'],
            'settings_default_language' => 'en',
        ]);

        $this->useConference($this->conference);

        Livewire::test(ConferenceMastHeadSetting::class)
            ->assertSeeHtml('wire:model="formData.name.fr"')
            ->assertDontSeeHtml('wire:model="formData.name.id"');
    }

    public function test_conference_frontend_switches_only_to_conference_languages(): void
    {
        $this->conference->setManyMeta([
            'settings_languages' => ['en', 'fr'],
            'settings_default_language' => 'en',
        ]);

        $this->useConference($this->conference);

        $parameters = ['conference' => $this->conference->path];

        $this->get(route('livewirePageGroup.conference.pages.set-locale', [...$parameters, 'lang' => 'fr']))
            ->assertSessionHas('locale', 'fr');

        $this->flushSession();

        $this->get(route('livewirePageGroup.conference.pages.set-locale', [...$parameters, 'lang' => 'id']))
            ->assertSessionMissing('locale');
    }
}
