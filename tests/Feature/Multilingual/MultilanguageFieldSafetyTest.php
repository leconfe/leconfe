<?php

namespace Tests\Feature\Multilingual;

use App\Models\User;
use App\Panel\Conference\Livewire\MastHeadSetting as ConferenceMastHeadSetting;
use App\Support\Multilingual\ContentLocales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class MultilanguageFieldSafetyTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    public function test_stored_translations_are_rendered_escaped_and_parsed_inertly(): void
    {
        $conference = $this->makeConference('Annual Conference');
        $conference->setMeta('about', '<p>About the conference</p>');
        $scheduledConference = $this->makeScheduledConference($conference, 'Annual Meeting 2026');

        $this->useConference($conference, $scheduledConference);
        $this->enableLanguages(['en', 'id']);

        $conference->setTranslation('name', 'id', '<img src=x onerror=alert(1)>');
        $conference->setTranslation('about', 'id', '<p><img src=x onerror=alert(2)></p>');

        $this->actingAs(User::query()->create([
            'given_name' => 'Site',
            'family_name' => 'Manager',
            'email' => 'manager@example.test',
            'password' => 'password123456',
        ]));

        $html = Livewire::test(ConferenceMastHeadSetting::class)
            ->assertSet('formData.name.id', '<img src=x onerror=alert(1)>')
            ->assertSet('formData.meta.about.id', '<p><img src=x onerror=alert(2)></p>')
            ->html();

        $this->assertStringContainsString('onerror=alert(1)', $html);
        $this->assertStringContainsString('onerror=alert(2)', $html);
        $this->assertStringNotContainsString('<img src=x onerror=', $this->withoutEscapedContexts($html));
        $this->assertStringContainsString('DOMParser', $html);

        $fieldView = file_get_contents(resource_path('views/filament/forms/components/multilanguage-field.blade.php'));

        $this->assertStringContainsString('DOMParser', $fieldView);
        $this->assertStringNotContainsString('innerHTML', $fieldView);
    }

    public function test_blank_detection_matches_the_client_side_rules(): void
    {
        $this->assertTrue(ContentLocales::isBlank(null));
        $this->assertTrue(ContentLocales::isBlank(''));
        $this->assertTrue(ContentLocales::isBlank('   '));
        $this->assertTrue(ContentLocales::isBlank('<p></p>'));
        $this->assertTrue(ContentLocales::isBlank('<p>&nbsp;</p>'));
        $this->assertTrue(ContentLocales::isBlank("<p>\u{00A0}</p><br>"));

        $this->assertFalse(ContentLocales::isBlank('Konferensi'));
        $this->assertFalse(ContentLocales::isBlank('<p>Tentang</p>'));
        $this->assertFalse(ContentLocales::isBlank('<p><img src="/logo.png"></p>'));
        $this->assertFalse(ContentLocales::isBlank('<iframe src="https://example.test"></iframe>'));
        $this->assertFalse(ContentLocales::isBlank('<table><tr><td></td></tr></table>'));
        $this->assertFalse(ContentLocales::isBlank('<hr>'));
    }

    /**
     * Remove attribute values and JSON/JS string literals, where an escaped copy of
     * the payload may legitimately appear, so only raw markup remains.
     */
    protected function withoutEscapedContexts(string $html): string
    {
        $html = preg_replace('/="[^"]*"/', '=""', $html);

        return preg_replace("/='[^']*'/", "=''", $html);
    }
}
