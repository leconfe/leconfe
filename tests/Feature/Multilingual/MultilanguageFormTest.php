<?php

namespace Tests\Feature\Multilingual;

use App\Actions\Site\SiteUpdateAction;
use App\Models\Conference;
use App\Models\ScheduledConference;
use App\Models\User;
use App\Panel\Administration\Livewire\SetupSetting as SiteSetupSetting;
use App\Panel\Conference\Livewire\LicenseSetting;
use App\Panel\Conference\Livewire\MastHeadSetting as ConferenceMastHeadSetting;
use App\Panel\Conference\Livewire\SetupSetting as ConferenceSetupSetting;
use App\Panel\ScheduledConference\Livewire\AppearanceSetupSetting;
use App\Panel\ScheduledConference\Livewire\MastHeadSetting as ScheduledConferenceMastHeadSetting;
use App\Panel\ScheduledConference\Livewire\PrivacySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RahmanRamsi\LaravelTranslatable\Translate;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class MultilanguageFormTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = $this->makeConference('Annual Conference');
        $this->conference->setMeta('about', '<p>About the conference</p>');
        $this->scheduledConference = $this->makeScheduledConference($this->conference, 'Annual Meeting 2026');
        $this->scheduledConference->setMeta('about', '<p>About the meeting</p>');

        $this->useConference($this->conference, $this->scheduledConference);

        $this->actingAs(User::query()->create([
            'given_name' => 'Site',
            'family_name' => 'Manager',
            'email' => 'manager@example.test',
            'password' => 'password123456',
        ]));
    }

    public function test_conference_masthead_renders_an_input_per_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(ConferenceMastHeadSetting::class)
            ->assertSet('formData.name.en', 'Annual Conference')
            ->assertSet('formData.name.id', null)
            ->assertSet('formData.meta.about.en', '<p>About the conference</p>')
            ->assertSeeHtml('wire:model="formData.name.en"')
            ->assertSeeHtml('wire:model="formData.name.id"')
            ->assertSeeHtml('formData.meta.description.id')
            ->assertSeeHtml('formData.meta.summary.id')
            ->assertSeeHtml('formData.meta.about.id')
            ->assertSee('Name (English)')
            ->assertSee('Name (Indonesian)')
            ->assertSeeHtml('lang="id"')
            ->assertSeeHtml('data-multilanguage-toggle');
    }

    public function test_conference_masthead_saves_every_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(ConferenceMastHeadSetting::class)
            ->set('formData.name.en', 'Annual Conference on Science')
            ->set('formData.name.id', 'Konferensi Tahunan Sains')
            ->set('formData.meta.about.id', '<p>Tentang konferensi</p>')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasNoErrors();

        $conference = Conference::query()->find($this->conference->getKey());

        $this->assertSame('Annual Conference on Science', $conference->name);
        $this->assertSame('Annual Conference on Science', $conference->translated('name', 'en'));
        $this->assertSame('Konferensi Tahunan Sains', $conference->translated('name', 'id'));
        $this->assertSame('<p>About the conference</p>', $conference->getMeta('about'));
        $this->assertSame('<p>Tentang konferensi</p>', $conference->translated('about', 'id'));
    }

    public function test_conference_masthead_requires_only_the_default_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(ConferenceMastHeadSetting::class)
            ->set('formData.name.en', 'Annual Conference')
            ->set('formData.name.id', '')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasNoErrors();

        Livewire::test(ConferenceMastHeadSetting::class)
            ->set('formData.name.en', '')
            ->set('formData.name.id', 'Konferensi Tahunan')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasErrors(['formData.name.en' => 'required'])
            ->assertHasNoErrors(['formData.name.id']);

        $this->assertSame('Annual Conference', Conference::query()->find($this->conference->getKey())->name);
    }

    public function test_scheduled_conference_masthead_renders_and_saves_every_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(ScheduledConferenceMastHeadSetting::class)
            ->assertSet('formData.title.en', 'Annual Meeting 2026')
            ->assertSet('formData.title.id', null)
            ->assertSeeHtml('wire:model="formData.title.en"')
            ->assertSeeHtml('wire:model="formData.title.id"')
            ->assertSeeHtml('formData.meta.conference_theme.id')
            ->assertSeeHtml('formData.meta.editorial_team.id')
            ->assertSeeHtml('formData.meta.about.id')
            ->set('formData.title.en', 'Annual Meeting 2026 Edition')
            ->set('formData.title.id', 'Pertemuan Tahunan 2026')
            ->set('formData.meta.about.id', '<p>Tentang pertemuan</p>')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasNoErrors();

        $scheduledConference = ScheduledConference::query()->find($this->scheduledConference->getKey());

        $this->assertSame('Annual Meeting 2026 Edition', $scheduledConference->title);
        $this->assertSame('Pertemuan Tahunan 2026', $scheduledConference->translated('title', 'id'));
        $this->assertSame('<p>About the meeting</p>', $scheduledConference->getMeta('about'));
        $this->assertSame('<p>Tentang pertemuan</p>', $scheduledConference->translated('about', 'id'));
    }

    public function test_scheduled_conference_masthead_requires_only_the_default_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(ScheduledConferenceMastHeadSetting::class)
            ->set('formData.title.en', '')
            ->set('formData.title.id', 'Pertemuan Tahunan')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasErrors(['formData.title.en' => 'required'])
            ->assertHasNoErrors(['formData.title.id']);

        Livewire::test(ScheduledConferenceMastHeadSetting::class)
            ->set('formData.title.en', 'Annual Meeting')
            ->set('formData.title.id', '')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasNoErrors();

        $this->assertSame('Annual Meeting', ScheduledConference::query()->find($this->scheduledConference->getKey())->title);
    }

    public function test_single_language_forms_keep_scalar_state(): void
    {
        $this->enableLanguages(['en']);

        Livewire::test(ConferenceMastHeadSetting::class)
            ->assertSet('formData.name', 'Annual Conference')
            ->assertSet('formData.meta.about', '<p>About the conference</p>')
            ->assertSeeHtml('wire:model="formData.name"')
            ->assertDontSeeHtml('formData.name.en')
            ->assertDontSeeHtml('data-multilanguage-toggle')
            ->set('formData.name', 'Renamed Conference')
            ->set('formData.meta.about', '<p>New about</p>')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasNoErrors();

        Livewire::test(ScheduledConferenceMastHeadSetting::class)
            ->assertSet('formData.title', 'Annual Meeting 2026')
            ->assertSeeHtml('wire:model="formData.title"')
            ->assertDontSeeHtml('formData.title.en')
            ->set('formData.title', 'Renamed Meeting')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasNoErrors();

        Livewire::test(ConferenceMastHeadSetting::class)
            ->set('formData.name', '')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasErrors(['formData.name' => 'required']);

        $conference = Conference::query()->find($this->conference->getKey());
        $this->assertSame('Renamed Conference', $conference->name);
        $this->assertSame('<p>New about</p>', $conference->getMeta('about'));
        $this->assertSame('Renamed Meeting', ScheduledConference::query()->find($this->scheduledConference->getKey())->title);
        $this->assertSame(0, Translate::query()->count());
    }

    public function test_single_language_save_keeps_an_existing_default_row_in_step(): void
    {
        $this->conference->setTranslations('about', ['en' => '<p>About (EN)</p>', 'id' => '<p>Tentang</p>']);
        $this->enableLanguages(['en']);

        Livewire::test(ConferenceMastHeadSetting::class)
            ->assertSet('formData.meta.about', '<p>About (EN)</p>')
            ->set('formData.meta.about', '<p>Updated about</p>')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasNoErrors();

        $conference = Conference::query()->find($this->conference->getKey());
        $this->assertSame('<p>Updated about</p>', $conference->translated('about', 'en'));
        $this->assertSame('<p>Tentang</p>', $conference->getStoredTranslation('about', 'id'));
    }

    public function test_other_settings_forms_render_an_input_per_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(ConferenceSetupSetting::class)
            ->assertSeeHtml('formData.meta.page_footer.en')
            ->assertSeeHtml('formData.meta.page_footer.id');

        Livewire::test(LicenseSetting::class)
            ->assertSeeHtml('formData.meta.license_terms.id');

        Livewire::test(AppearanceSetupSetting::class)
            ->assertSeeHtml('formData.meta.page_footer.id');

        Livewire::test(PrivacySetting::class)
            ->assertSeeHtml('formData.meta.privacy_statement.id');

        $this->useSiteContext();

        Livewire::test(SiteSetupSetting::class)
            ->assertSeeHtml('wire:model="formData.meta.name.en"')
            ->assertSeeHtml('wire:model="formData.meta.name.id"')
            ->assertSeeHtml('formData.meta.description.id');
    }

    public function test_site_update_saves_every_language(): void
    {
        $this->useSiteContext();
        $this->enableLanguages(['en', 'id']);

        SiteUpdateAction::run([
            'meta' => [
                'name' => ['en' => 'Conference Portal', 'id' => 'Portal Konferensi'],
                'description' => ['en' => 'Portal description', 'id' => null],
            ],
        ]);

        $site = app()->getSite();
        $this->assertSame('Conference Portal', $site->getMeta('name'));
        $this->assertSame('Portal Konferensi', $site->translated('name', 'id'));
        $this->assertSame('Portal description', $site->translated('description', 'id'));
    }

    protected function useSiteContext(): void
    {
        app()->setCurrentConferenceId(0);
        app()->setCurrentScheduledConferenceId(0);
    }
}
