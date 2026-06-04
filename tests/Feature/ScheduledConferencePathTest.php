<?php

namespace Tests\Feature;

use App\Frontend\Website\Pages\Home as WebsiteHome;
use App\Models\Conference;
use App\Models\ScheduledConference;
use App\Models\Site;
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

    public function test_website_home_loads_faculties_from_site_master_list(): void
    {
        Site::query()->create()->setMeta('scheduled_conference_faculties', [
            'Engineering',
            'Medicine',
            'Business',
        ]);

        $page = new WebsiteHome();
        $page->filter['faculty']['search'] = 'med';

        $page->loadFaculties();

        $this->assertSame(['Medicine'], $page->filter['faculty']['options']->all());
    }

    public function test_website_home_filters_scheduled_conferences_by_faculty(): void
    {
        Site::query()->create()->setMeta('scheduled_conference_faculties', [
            'Engineering',
            'Medicine',
        ]);

        $conference = Conference::query()->create([
            'name' => 'Test Conference',
            'path' => 'test-conference',
        ]);

        $engineering = ScheduledConference::query()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Engineering Conference',
            'path' => 'engineering',
            'is_published' => true,
        ]);
        $engineering->setMeta('faculty', 'Engineering');

        $medicine = ScheduledConference::query()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Medicine Conference',
            'path' => 'medicine',
            'is_published' => true,
        ]);
        $medicine->setMeta('faculty', 'Medicine');

        $page = new WebsiteHome();
        $page->filter['faculty']['value'] = ['Engineering'];

        $method = new \ReflectionMethod($page, 'getViewData');
        $method->setAccessible(true);
        $viewData = $method->invoke($page);

        $this->assertTrue($viewData['scheduledConferences']->contains($engineering));
        $this->assertFalse($viewData['scheduledConferences']->contains($medicine));
    }
}
