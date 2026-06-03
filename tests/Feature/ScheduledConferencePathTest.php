<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\ScheduledConference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledConferencePathTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_conference_lookup_requires_exact_path_casing(): void
    {
        $conference = Conference::query()->create([
            'name' => 'Test Conference',
            'path' => 'test-conference',
        ]);

        $scheduledConference = ScheduledConference::query()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Case Sensitive Series',
            'path' => 'Conf2026',
        ]);

        $this->assertTrue(
            $scheduledConference->is(ScheduledConference::findByConferenceAndExactPath($conference, 'Conf2026'))
        );
        $this->assertNull(ScheduledConference::findByConferenceAndExactPath($conference, 'conf2026'));
    }

    public function test_scheduled_conference_lookup_stays_within_its_conference(): void
    {
        $firstConference = Conference::query()->create([
            'name' => 'First Conference',
            'path' => 'first-conference',
        ]);
        $secondConference = Conference::query()->create([
            'name' => 'Second Conference',
            'path' => 'second-conference',
        ]);

        $firstScheduledConference = ScheduledConference::query()->create([
            'conference_id' => $firstConference->getKey(),
            'title' => 'Shared Path First',
            'path' => 'shared-path',
        ]);
        $secondScheduledConference = ScheduledConference::query()->create([
            'conference_id' => $secondConference->getKey(),
            'title' => 'Shared Path Second',
            'path' => 'shared-path',
        ]);

        $this->assertTrue(
            $firstScheduledConference->is(ScheduledConference::findByConferenceAndExactPath($firstConference, 'shared-path'))
        );
        $this->assertTrue(
            $secondScheduledConference->is(ScheduledConference::findByConferenceAndExactPath($secondConference, 'shared-path'))
        );
    }
}
