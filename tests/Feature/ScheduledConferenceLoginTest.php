<?php

namespace Tests\Feature;

use App\Frontend\ScheduledConference\Pages\Login;
use App\Models\Conference;
use App\Models\ScheduledConference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledConferenceLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_conference_login_password_can_be_revealed(): void
    {
        $scheduledConference = $this->makeScheduledConference();

        $this->withoutVite()
            ->get(route(Login::getRouteName('scheduledConference'), [
                'conference' => $scheduledConference->conference->path,
                'serie' => $scheduledConference->path,
            ]))
            ->assertOk()
            ->assertSee('fi-input', false)
            ->assertSee('Show password', false)
            ->assertSee('Hide password', false);
    }

    public function test_scheduled_conference_login_hides_register_action_when_registration_is_disabled(): void
    {
        $scheduledConference = $this->makeScheduledConference();
        $scheduledConference->setMeta('allow_registration', false);

        $this->withoutVite()
            ->get(route(Login::getRouteName('scheduledConference'), [
                'conference' => $scheduledConference->conference->path,
                'serie' => $scheduledConference->path,
            ]))
            ->assertOk()
            ->assertDontSee(__('general.register'));
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
