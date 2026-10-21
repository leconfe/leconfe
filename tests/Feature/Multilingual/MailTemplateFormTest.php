<?php

namespace Tests\Feature\Multilingual;

use App\Facades\Setting;
use App\Mail\Templates\TestMail;
use App\Models\Conference;
use App\Models\DefaultMailTemplate;
use App\Models\MailTemplate;
use App\Models\ScheduledConference;
use App\Models\User;
use App\Panel\Conference\Livewire\EmailSetting;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use RahmanRamsi\LaravelTranslatable\Translate;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class MailTemplateFormTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected function setUp(): void
    {
        parent::setUp();

        app()->getSite();

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);

        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));
        Gate::before(fn () => true);

        $this->actingAs(User::query()->create([
            'given_name' => 'Template',
            'family_name' => 'Manager',
            'email' => 'template-manager@example.test',
            'password' => 'password123456',
        ]));
    }

    public function test_template_form_renders_and_saves_every_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        $this->editTestMail()
            ->assertSeeHtml('mountedTableActionsData.0.subject.en')
            ->assertSeeHtml('mountedTableActionsData.0.subject.id')
            ->assertSeeHtml('mountedTableActionsData.0.html_template.en')
            ->assertSeeHtml('mountedTableActionsData.0.html_template.id')
            ->assertTableActionDataSet([
                'subject' => ['en' => TestMail::getDefaultSubject(), 'id' => null],
                'html_template' => ['en' => TestMail::getDefaultHtmlTemplate(), 'id' => null],
            ])
            ->setTableActionData([
                'subject' => ['en' => 'Test from {{ conferenceName }}', 'id' => 'Uji dari {{ conferenceName }}'],
                'html_template' => ['en' => '<p>English body</p>', 'id' => '<p>Isi bahasa Indonesia</p>'],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $template = $this->storedTestMailTemplate();

        $this->assertSame('Test from {{ conferenceName }}', $template->subject);
        $this->assertSame('<p>English body</p>', $template->html_template);
        $this->assertSame('English body', $template->text_template);
        $this->assertSame('Uji dari {{ conferenceName }}', $template->getStoredTranslation('subject', 'id'));
        $this->assertSame('<p>Isi bahasa Indonesia</p>', $template->getStoredTranslation('html_template', 'id'));

        App::setLocale('id');
        $this->assertSame('Uji dari {{ conferenceName }}', $template->getSubject());
        $this->assertSame('Isi bahasa Indonesia', $template->getTextTemplate());

        App::setLocale('en');
        $this->assertSame('English body', $template->getTextTemplate());

        $this->editTestMail()
            ->assertTableActionDataSet([
                'subject' => ['en' => 'Test from {{ conferenceName }}', 'id' => 'Uji dari {{ conferenceName }}'],
                'html_template' => ['en' => '<p>English body</p>', 'id' => '<p>Isi bahasa Indonesia</p>'],
            ])
            ->setTableActionData([
                'subject' => ['en' => 'Test from {{ conferenceName }}', 'id' => 'Uji ulang'],
                'html_template' => ['en' => '<p>English body, edited</p>', 'id' => '<p>Isi bahasa Indonesia</p>'],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $template = $this->storedTestMailTemplate();

        $this->assertSame(1, MailTemplate::query()->where('mailable', TestMail::class)->count());
        $this->assertSame('English body, edited', $template->text_template);
        $this->assertSame('Uji ulang', $template->getStoredTranslation('subject', 'id'));
    }

    public function test_template_form_with_one_language_saves_as_before(): void
    {
        $this->enableLanguages(['en']);

        $this->editTestMail()
            ->assertDontSeeHtml('mountedTableActionsData.0.subject.en')
            ->assertTableActionDataSet([
                'subject' => TestMail::getDefaultSubject(),
                'html_template' => TestMail::getDefaultHtmlTemplate(),
            ])
            ->setTableActionData([
                'subject' => 'Single language subject',
                'html_template' => "<p>Single\n    language body</p>",
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $template = $this->storedTestMailTemplate();

        $this->assertSame($this->conference->getKey(), $template->conference_id);
        $this->assertSame('Single language subject', $template->subject);
        $this->assertSame("<p>Single\n    language body</p>", $template->html_template);
        $this->assertSame("Single\nlanguage body", $template->text_template);
        $this->assertSame(DefaultMailTemplate::query()->where('mailable', TestMail::class)->value('description'), $template->description);
        $this->assertSame(0, Translate::query()->where('translatable_type', $template->getMorphClass())->count());
    }

    public function test_restoring_the_default_template_removes_its_translations(): void
    {
        $this->enableLanguages(['en', 'id']);

        $template = MailTemplate::query()->create([
            'conference_id' => $this->conference->getKey(),
            'mailable' => TestMail::class,
            'subject' => 'Custom',
            'html_template' => '<p>Custom</p>',
        ]);
        $template->setTranslation('subject', 'id', 'Kustom');

        $this->assertSame(1, $template->translate()->count());

        Livewire::test(EmailSetting::class)
            ->callTableAction('restoreDefault', $this->defaultRecord());

        $this->assertSame(0, MailTemplate::query()->where('mailable', TestMail::class)->count());
        $this->assertSame(0, Translate::query()
            ->where('translatable_type', $template->getMorphClass())
            ->where('translatable_id', $template->getKey())
            ->count());
    }

    public function test_layout_form_saves_and_mounts_every_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        $this->saveLayout(
            Livewire::test(EmailSetting::class)
                ->assertSeeHtml('layoutTemplateFormData.mail_header.en')
                ->assertSeeHtml('layoutTemplateFormData.mail_header.id')
                ->assertSeeHtml('layoutTemplateFormData.mail_footer.id')
                ->set('layoutTemplateFormData.mail_header', ['en' => '<p>Header</p>', 'id' => '<p>Kepala</p>'])
                ->set('layoutTemplateFormData.mail_footer', ['en' => '<p>Footer</p>', 'id' => '<p>Kaki</p>'])
        );

        $conference = Conference::query()->find($this->conference->getKey());

        $this->assertSame('<p>Header</p>', Setting::get('mail_header'));
        $this->assertSame('<p>Footer</p>', Setting::get('mail_footer'));
        $this->assertSame('<p>Kepala</p>', $conference->getStoredTranslation('settings_mail_header', 'id'));
        $this->assertSame('<p>Kaki</p>', $conference->getStoredTranslation('settings_mail_footer', 'id'));

        App::setLocale('id');
        $layout = MailTemplate::renderLayout();
        $this->assertStringContainsString('<p>Kepala</p>', $layout);
        $this->assertStringContainsString('<p>Kaki</p>', $layout);

        Livewire::test(EmailSetting::class)
            ->assertSet('layoutTemplateFormData.mail_header', ['en' => '<p>Header</p>', 'id' => '<p>Kepala</p>'])
            ->assertSet('layoutTemplateFormData.mail_footer', ['en' => '<p>Footer</p>', 'id' => '<p>Kaki</p>']);
    }

    public function test_layout_form_with_one_language_saves_as_before(): void
    {
        $this->enableLanguages(['en']);

        $this->saveLayout(
            Livewire::test(EmailSetting::class)
                ->assertDontSeeHtml('layoutTemplateFormData.mail_header.en')
                ->set('layoutTemplateFormData.mail_header', '<p>Only header</p>')
                ->set('layoutTemplateFormData.mail_footer', '<p>Only footer</p>')
        );

        $conference = Conference::query()->find($this->conference->getKey());

        $this->assertSame('<p>Only header</p>', $conference->getMeta('settings_mail_header'));
        $this->assertSame('<p>Only footer</p>', $conference->getMeta('settings_mail_footer'));
        $this->assertSame(0, $conference->translate()->whereIn('key', ['settings_mail_header', 'settings_mail_footer'])->count());

        Livewire::test(EmailSetting::class)
            ->assertSet('layoutTemplateFormData.mail_header', '<p>Only header</p>')
            ->assertSet('layoutTemplateFormData.mail_footer', '<p>Only footer</p>');
    }

    protected function editTestMail(): Testable
    {
        return Livewire::test(EmailSetting::class)
            ->mountTableAction('edit', $this->defaultRecord());
    }

    protected function saveLayout(Testable $component): Testable
    {
        return $component
            ->callFormComponentAction('saveLayoutFormAction', 'saveLayoutForm', formName: 'layoutTemplateForm')
            ->assertHasNoFormComponentActionErrors();
    }

    protected function defaultRecord(): DefaultMailTemplate
    {
        return DefaultMailTemplate::query()->where('mailable', TestMail::class)->firstOrFail();
    }

    protected function storedTestMailTemplate(): MailTemplate
    {
        return MailTemplate::query()
            ->where('conference_id', $this->conference->getKey())
            ->where('mailable', TestMail::class)
            ->firstOrFail();
    }
}
