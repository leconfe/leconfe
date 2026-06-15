<?php

namespace Tests\Feature;

use App\Frontend\ScheduledConference\Pages\Register;
use App\Models\Conference;
use App\Models\ScheduledConference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduledConferenceRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_statement_link_uses_panel_generated_link_class(): void
    {
        $scheduledConference = $this->makeScheduledConference();

        $this->withoutVite()
            ->get(route(Register::getRouteName('scheduledConference'), [
                'conference' => $scheduledConference->conference->path,
                'serie' => $scheduledConference->path,
            ]))
            ->assertOk()
            ->assertSee('class="fi-simple-link"', false)
            ->assertSee('Show password', false)
            ->assertSee('Hide password', false)
            ->assertDontSee('link link-primary', false);
    }

    public function test_registration_page_shows_closed_message_when_registration_is_disabled(): void
    {
        $scheduledConference = $this->makeScheduledConference();
        $scheduledConference->setMeta('allow_registration', false);

        $this->withoutVite()
            ->get(route(Register::getRouteName('scheduledConference'), [
                'conference' => $scheduledConference->conference->path,
                'serie' => $scheduledConference->path,
            ]))
            ->assertOk()
            ->assertSee(__('general.registration_closed'))
            ->assertDontSee('wire:submit="register"', false);
    }

    public function test_registration_submission_is_ignored_when_registration_is_disabled(): void
    {
        $scheduledConference = $this->makeScheduledConference();
        $scheduledConference->setMeta('allow_registration', false);

        Livewire::test(Register::class)
            ->set('email', 'closed-registration@example.test')
            ->call('register')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('users', [
            'email' => 'closed-registration@example.test',
        ]);
    }

    protected function makeScheduledConference(): ScheduledConference
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

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());

        return $scheduledConference;
    }
}
