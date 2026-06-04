<?php

namespace Tests\Feature;

use App\Frontend\ScheduledConference\Pages\Login;
use App\Frontend\ScheduledConference\Pages\PrivacyStatement;
use App\Models\Conference;
use App\Models\ScheduledConference;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduledConferencePrivacyStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_statement_uses_simple_auth_layout_without_website_theme(): void
    {
        $scheduledConference = $this->makeScheduledConferenceWithPrivacyStatement('<p>Personal data processing details.</p>');

        $this->assertSame(Login::getLayout(), PrivacyStatement::getLayout());
        $this->assertSame(MaxWidth::FourExtraLarge, $this->getPrivacyStatementLayoutData()['maxWidth']);

        Livewire::test(PrivacyStatement::class)
            ->assertSeeHtml('fi-simple-page')
            ->assertSeeHtml('Back to home')
            ->assertSeeHtml('<p>Personal data processing details.</p>')
            ->assertDontSeeHtml('website::layouts.main');

        $this->withoutVite()
            ->get(route(PrivacyStatement::getRouteName('scheduledConference'), [
                'conference' => $scheduledConference->conference->path,
                'serie' => $scheduledConference->path,
            ]))
            ->assertOk()
            ->assertSee('fi-simple-layout', false)
            ->assertSee('fi-simple-page', false)
            ->assertSee('<p>Personal data processing details.</p>', false);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getPrivacyStatementLayoutData(): array
    {
        return (function (): array {
            return $this->getLayoutData();
        })->call(new PrivacyStatement);
    }

    protected function makeScheduledConferenceWithPrivacyStatement(string $privacyStatement): ScheduledConference
    {
        $conference = Conference::query()->create([
            'name' => 'Test Conference',
            'path' => 'test-conference',
        ]);

        $scheduledConference = ScheduledConference::query()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Test Scheduled Conference',
            'path' => 'test-scheduled-conference',
            'is_published' => true,
        ]);
        $scheduledConference->setMeta('privacy_statement', $privacyStatement);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());

        return $scheduledConference;
    }
}
