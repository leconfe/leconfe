<?php

namespace Tests\Feature\Multilingual;

use App\Models\Conference;
use App\Models\User;
use App\Panel\Conference\Livewire\MastHeadSetting as ConferenceMastHeadSetting;
use App\Panel\ScheduledConference\Resources\TimelineResource\Pages\ManageTimeline;
use App\Providers\PanelProvider;
use DOMDocument;
use DOMXPath;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class MultilanguageFieldStatusTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected Conference $conference;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = $this->makeConference('Annual Conference');
        $this->conference->setMeta('about', '<p>About the conference</p>');
        $this->conference->setTranslations('about', ['en' => '<p>About the conference</p>', 'fr' => '<p>À propos de la conférence</p>']);

        $this->useConference($this->conference);
        $this->enableLanguages(['en', 'fr']);

        $this->actingAs(User::query()->create([
            'given_name' => 'Site',
            'family_name' => 'Manager',
            'email' => 'manager@example.test',
            'password' => 'password123456',
        ]));
    }

    public function test_each_field_shows_the_status_of_its_french_copy_without_opening_translations(): void
    {
        $html = Livewire::test(ConferenceMastHeadSetting::class)->html();

        $this->assertSame(['French' => 'Fallback: English'], $this->visibleStatuses($html, 'formData.name.fr'));
        $this->assertSame(['French' => 'Complete'], $this->visibleStatuses($html, 'formData.meta.about.fr'));
        $this->assertSame(['French' => 'Missing'], $this->visibleStatuses($html, 'formData.meta.description.fr'));
        $this->assertFalse($this->translationsAreOpen($html, 'formData.name.fr'));
    }

    public function test_statuses_are_translated_in_french(): void
    {
        app()->setLocale('fr');

        $html = Livewire::test(ConferenceMastHeadSetting::class)->html();

        $this->assertSame(['Français' => 'Langue de secours : Anglais'], $this->visibleStatuses($html, 'formData.name.fr'));
        $this->assertSame(['Français' => 'Complet'], $this->visibleStatuses($html, 'formData.meta.about.fr'));
        $this->assertSame(['Français' => 'Manquant'], $this->visibleStatuses($html, 'formData.meta.description.fr'));
    }

    public function test_a_translation_that_fails_validation_opens_the_translations_and_shows_an_error_status(): void
    {
        $this->useConference($this->conference, $this->makeScheduledConference($this->conference));
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));
        Gate::before(fn () => true);
        app()->setLocale('fr');

        $html = Livewire::test(ManageTimeline::class)
            ->mountAction('create')
            ->setActionData([
                'name' => ['en' => 'Deadline', 'fr' => 'Date limite'],
                'description' => ['en' => 'Last day to submit', 'fr' => str_repeat('a', 300)],
                'date' => now()->addWeek()->toDateString(),
            ])
            ->callMountedAction()
            ->assertHasActionErrors(['description.fr' => 'max'])
            ->html();

        $this->assertSame(['Français' => 'Erreur'], $this->visibleStatuses($html, 'mountedActionsData.0.description.fr'));
        $this->assertTrue($this->opensOnError($html, 'mountedActionsData.0.description.fr'));
        $this->assertSame(['Français' => 'Complet'], $this->visibleStatuses($html, 'mountedActionsData.0.name.fr'));
        $this->assertFalse($this->opensOnError($html, 'mountedActionsData.0.name.fr'));
    }

    /**
     * @return array<string, string>
     */
    protected function visibleStatuses(string $html, string $translationPath): array
    {
        $xpath = $this->xpath($html);
        $field = $this->field($xpath, $translationPath);
        $statuses = [];

        foreach ($xpath->query('.//*[@data-multilanguage-status]/li', $field) as $item) {
            $language = trim($item->firstElementChild->textContent);
            $visible = $xpath->query('./*[@data-status][not(contains(@style, "display: none"))]', $item);

            $this->assertCount(1, $visible, "Exactly one status is shown for {$language}.");
            $statuses[$language] = trim($visible->item(0)->textContent);
        }

        return $statuses;
    }

    protected function translationsAreOpen(string $html, string $translationPath): bool
    {
        $xpath = $this->xpath($html);
        $panel = $xpath->query('./*[starts-with(@id, "translations-")]', $this->field($xpath, $translationPath))->item(0);

        return ! str_contains((string) $panel->getAttribute('style'), 'display: none');
    }

    protected function opensOnError(string $html, string $translationPath): bool
    {
        $xpath = $this->xpath($html);

        return $xpath->query('./*[@data-multilanguage-open-on-error]', $this->field($xpath, $translationPath))->length === 1;
    }

    protected function field(DOMXPath $xpath, string $translationPath): \DOMElement
    {
        $field = $xpath->query("//*[contains(@class, 'fi-fo-multilanguage-field')][contains(@x-data, '{$translationPath}')]")->item(0);

        $this->assertNotNull($field, "Multilanguage field for {$translationPath} is rendered.");

        return $field;
    }

    protected function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }
}
