<?php

namespace Tests\Feature;

use App\Frontend\ScheduledConference\Pages\Register;
use App\Models\Conference;
use App\Models\ScheduledConference;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
