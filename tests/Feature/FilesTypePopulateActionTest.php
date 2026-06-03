<?php

namespace Tests\Feature;

use App\Actions\SubmissionFiles\FilesTypePopulateAction;
use App\Models\Conference;
use App\Models\ScheduledConference;
use App\Models\SubmissionFileType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilesTypePopulateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_populates_four_default_submission_file_types(): void
    {
        $scheduledConference = $this->makeScheduledConference();

        FilesTypePopulateAction::run($scheduledConference);
        FilesTypePopulateAction::run($scheduledConference);

        $this->assertSame(
            ['Abstract', 'Full Paper', 'Poster', 'Other'],
            SubmissionFileType::withoutGlobalScopes()
                ->where('scheduled_conference_id', $scheduledConference->getKey())
                ->ordered()
                ->pluck('name')
                ->all()
        );
    }

    protected function makeScheduledConference(): ScheduledConference
    {
        $conference = Conference::query()->create([
            'name' => 'Conference',
            'path' => 'conference',
        ]);

        return ScheduledConference::withoutEvents(fn () => ScheduledConference::withoutGlobalScopes()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Scheduled Conference',
            'path' => 'scheduled-conference',
            'date_start' => now()->toDateString(),
            'date_end' => now()->addDays(2)->toDateString(),
        ]));
    }
}
