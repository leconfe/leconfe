<?php

namespace Tests\Feature;

use App\Constants\SubmissionFileCategory;
use App\Models\Conference;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\SubmissionFileType;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Steps\UploadFilesStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubmissionWizardRequiredFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_step_blocks_until_each_required_file_type_has_an_upload(): void
    {
        $context = $this->makeSubmissionContext();
        $submission = $context['submission'];

        $manuscript = SubmissionFileType::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
            'name' => 'Manuscript',
            'required' => true,
        ]);

        SubmissionFileType::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
            'name' => 'Supplementary',
            'required' => false,
        ]);

        $this->actingAs($context['user']);

        Livewire::test(UploadFilesStep::class, ['record' => $submission])
            ->call('mountAction', 'nextStep')
            ->assertNotDispatched('next-wizard-step');

        $media = $submission->addMedia(resource_path('assets/sample.pdf'))
            ->preservingOriginal()
            ->toMediaCollection(SubmissionFileCategory::ABSTRACT_FILES, 'private-files');

        SubmissionFile::query()->create([
            'submission_id' => $submission->getKey(),
            'media_id' => $media->getKey(),
            'submission_file_type_id' => $manuscript->getKey(),
            'user_id' => $context['user']->getKey(),
            'category' => SubmissionFileCategory::ABSTRACT_FILES,
        ]);

        Livewire::test(UploadFilesStep::class, ['record' => $submission->refresh()])
            ->call('mountAction', 'nextStep')
            ->assertDispatched('next-wizard-step');
    }

    protected function makeSubmissionContext(): array
    {
        $conference = Conference::query()->create([
            'name' => 'Conference',
            'path' => 'conference',
        ]);

        $scheduledConference = ScheduledConference::withoutGlobalScopes()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'Scheduled Conference',
            'path' => 'scheduled-conference',
            'date_start' => now()->toDateString(),
            'date_end' => now()->addDays(2)->toDateString(),
        ]);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());

        $track = Track::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'title' => 'Track',
            'abbreviation' => 'TRK',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'given_name' => 'Author',
            'family_name' => 'Tester',
            'email' => 'author@example.test',
            'password' => 'password123456',
        ]);

        $submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $user->getKey(),
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'track_id' => $track->getKey(),
        ]);

        return [
            'conference' => $conference,
            'scheduledConference' => $scheduledConference,
            'track' => $track,
            'user' => $user,
            'submission' => $submission,
        ];
    }
}
