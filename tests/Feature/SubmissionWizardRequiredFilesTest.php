<?php

namespace Tests\Feature;

use App\Constants\SubmissionFileCategory;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\SubmissionFileType;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\AbstractFiles;
use App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Steps\UploadFilesStep;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
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

        $ethicsApproval = SubmissionFileType::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
            'name' => 'Ethics Approval',
            'required' => true,
        ]);

        SubmissionFileType::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
            'name' => 'Supplementary',
            'required' => false,
        ]);

        $this->actingAs($context['user']);

        $uploadStep = new UploadFilesStep;
        $uploadStep->record = $submission;

        $this->assertSame(
            ['Manuscript', 'Ethics Approval'],
            $uploadStep->missingRequiredUploadTypeNames()->all()
        );

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

        $this->assertSame(
            ['Ethics Approval'],
            $uploadStep->missingRequiredUploadTypeNames()->all()
        );

        Livewire::test(UploadFilesStep::class, ['record' => $submission->refresh()])
            ->call('mountAction', 'nextStep')
            ->assertNotDispatched('next-wizard-step');

        $media = $submission->addMedia(resource_path('assets/sample.pdf'))
            ->preservingOriginal()
            ->toMediaCollection(SubmissionFileCategory::ABSTRACT_FILES, 'private-files');

        SubmissionFile::query()->create([
            'submission_id' => $submission->getKey(),
            'media_id' => $media->getKey(),
            'submission_file_type_id' => $ethicsApproval->getKey(),
            'user_id' => $context['user']->getKey(),
            'category' => SubmissionFileCategory::ABSTRACT_FILES,
        ]);

        Livewire::test(UploadFilesStep::class, ['record' => $submission->refresh()])
            ->call('mountAction', 'nextStep')
            ->assertDispatched('next-wizard-step');
    }

    public function test_upload_step_shows_required_file_type_statuses(): void
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
            'name' => 'Ethics Approval',
            'required' => true,
        ]);

        SubmissionFileType::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
            'name' => 'Supplementary',
            'required' => false,
        ]);

        $this->actingAs($context['user']);

        Livewire::test(UploadFilesStep::class, ['record' => $submission])
            ->assertSee('Required files')
            ->assertSee('Manuscript')
            ->assertSee('Ethics Approval')
            ->assertSee('Not uploaded')
            ->assertSeeHtml('bg-danger-600 text-white')
            ->assertSeeHtml('required-upload-missing-icon h-4 w-4 text-white')
            ->assertDontSee('Supplementary');

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
            ->assertSee('Manuscript')
            ->assertSee('Uploaded')
            ->assertSee('Ethics Approval')
            ->assertSee('Not uploaded');
    }

    public function test_uploading_submission_file_dispatches_refresh_event_for_required_file_statuses(): void
    {
        $context = $this->makeSubmissionContext();
        $submission = $context['submission'];

        $manuscript = SubmissionFileType::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
            'name' => 'Manuscript',
            'required' => true,
        ]);

        $this->actingAs($context['user']);

        $media = $submission->addMedia(resource_path('assets/sample.pdf'))
            ->preservingOriginal()
            ->toMediaCollection(SubmissionFileCategory::ABSTRACT_FILES, 'private-files');

        Livewire::test(AbstractFiles::class, ['submission' => $submission])
            ->set('uploadFilesData', [
                ['files' => [$media->uuid]],
            ])
            ->call('handleUploadAction', ['type' => $manuscript->getKey()], TableAction::make('upload'))
            ->assertDispatched('refreshLivewire');
    }

    public function test_deleting_submission_file_dispatches_refresh_event_for_required_file_statuses(): void
    {
        $context = $this->makeSubmissionContext();
        $submission = $context['submission'];

        $manuscript = SubmissionFileType::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
            'name' => 'Manuscript',
            'required' => true,
        ]);

        $this->actingAs($context['user']);
        Gate::before(fn () => true);

        $media = $submission->addMedia(resource_path('assets/sample.pdf'))
            ->preservingOriginal()
            ->toMediaCollection(SubmissionFileCategory::ABSTRACT_FILES, 'private-files');

        $submissionFile = SubmissionFile::query()->create([
            'submission_id' => $submission->getKey(),
            'media_id' => $media->getKey(),
            'submission_file_type_id' => $manuscript->getKey(),
            'user_id' => $context['user']->getKey(),
            'category' => SubmissionFileCategory::ABSTRACT_FILES,
        ]);

        Livewire::test(AbstractFiles::class, ['submission' => $submission])
            ->callTableAction('delete', $submissionFile)
            ->assertDispatched('refreshLivewire');
    }

    public function test_author_can_delete_submission_file_from_upload_wizard(): void
    {
        $context = $this->makeSubmissionContext();
        $submission = $context['submission'];

        $manuscript = SubmissionFileType::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
            'name' => 'Manuscript',
            'required' => true,
        ]);

        $authorRole = Role::withoutGlobalScopes()->firstOrCreate([
            'name' => UserRole::Author->value,
            'guard_name' => 'web',
            'conference_id' => $context['conference']->getKey(),
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
        ]);

        $submission->participants()->create([
            'user_id' => $context['user']->getKey(),
            'role_id' => $authorRole->getKey(),
        ]);

        $this->actingAs($context['user']);
        $this->assertTrue($submission->isParticipant($context['user']));
        $this->assertTrue($context['user']->can('uploadAbstract', $submission));
        $this->assertTrue($context['user']->can('deleteFile', $submission));

        $media = $submission->addMedia(resource_path('assets/sample.pdf'))
            ->preservingOriginal()
            ->toMediaCollection(SubmissionFileCategory::ABSTRACT_FILES, 'private-files');

        $submissionFile = SubmissionFile::query()->create([
            'submission_id' => $submission->getKey(),
            'media_id' => $media->getKey(),
            'submission_file_type_id' => $manuscript->getKey(),
            'user_id' => $context['user']->getKey(),
            'category' => SubmissionFileCategory::ABSTRACT_FILES,
        ]);

        Livewire::test(AbstractFiles::class, ['submission' => $submission])
            ->callTableAction('delete', $submissionFile)
            ->assertDispatched('refreshLivewire');

        $this->assertDatabaseMissing('submission_files', [
            'id' => $submissionFile->getKey(),
        ]);
    }

    public function test_author_cannot_manage_submission_files_after_wizard_is_completed(): void
    {
        $context = $this->makeSubmissionContext();
        $submission = $context['submission'];

        $manuscript = SubmissionFileType::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
            'name' => 'Manuscript',
            'required' => true,
        ]);

        $authorRole = Role::withoutGlobalScopes()->firstOrCreate([
            'name' => UserRole::Author->value,
            'guard_name' => 'web',
            'conference_id' => $context['conference']->getKey(),
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
        ]);

        $submission->participants()->create([
            'user_id' => $context['user']->getKey(),
            'role_id' => $authorRole->getKey(),
        ]);

        $submission->forceFill([
            'stage' => SubmissionStage::CallforAbstract,
            'status' => SubmissionStatus::Queued,
        ])->save();

        $media = $submission->addMedia(resource_path('assets/sample.pdf'))
            ->preservingOriginal()
            ->toMediaCollection(SubmissionFileCategory::ABSTRACT_FILES, 'private-files');

        $submissionFile = SubmissionFile::query()->create([
            'submission_id' => $submission->getKey(),
            'media_id' => $media->getKey(),
            'submission_file_type_id' => $manuscript->getKey(),
            'user_id' => $context['user']->getKey(),
            'category' => SubmissionFileCategory::ABSTRACT_FILES,
        ]);

        $this->actingAs($context['user']);
        $this->assertTrue($submission->isParticipant($context['user']));
        $this->assertFalse($context['user']->can('uploadAbstract', $submission));
        $this->assertFalse($context['user']->can('deleteFile', $submission));

        Livewire::test(AbstractFiles::class, ['submission' => $submission->refresh()])
            ->assertTableActionHidden('upload')
            ->assertTableActionHidden('rename', $submissionFile)
            ->assertTableActionHidden('delete', $submissionFile);
    }

    public function test_submission_file_table_loads_submission_for_delete_authorization(): void
    {
        $context = $this->makeSubmissionContext();
        $submission = $context['submission'];

        $manuscript = SubmissionFileType::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
            'name' => 'Manuscript',
            'required' => true,
        ]);

        $this->actingAs($context['user']);
        Gate::before(fn () => true);

        $media = $submission->addMedia(resource_path('assets/sample.pdf'))
            ->preservingOriginal()
            ->toMediaCollection(SubmissionFileCategory::ABSTRACT_FILES, 'private-files');

        $submissionFile = SubmissionFile::query()->create([
            'submission_id' => $submission->getKey(),
            'media_id' => $media->getKey(),
            'submission_file_type_id' => $manuscript->getKey(),
            'user_id' => $context['user']->getKey(),
            'category' => SubmissionFileCategory::ABSTRACT_FILES,
        ]);

        $record = Livewire::test(AbstractFiles::class, ['submission' => $submission])
            ->instance()
            ->tableQuery()
            ->whereKey($submissionFile->getKey())
            ->firstOrFail();

        $this->assertTrue($record->relationLoaded('submission'));
        $this->assertTrue($context['user']->can('deleteFile', $record->submission));
    }

    public function test_submission_file_table_shows_when_the_file_was_uploaded(): void
    {
        $context = $this->makeSubmissionContext();
        $submission = $context['submission'];

        $manuscript = SubmissionFileType::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
            'name' => 'Manuscript',
            'required' => true,
        ]);

        $this->actingAs($context['user']);

        $media = $submission->addMedia(resource_path('assets/sample.pdf'))
            ->preservingOriginal()
            ->toMediaCollection(SubmissionFileCategory::ABSTRACT_FILES, 'private-files');

        $uploadedAt = now()->setDate(2026, 1, 15)->setTime(9, 30);

        $submissionFile = SubmissionFile::query()->create([
            'submission_id' => $submission->getKey(),
            'media_id' => $media->getKey(),
            'submission_file_type_id' => $manuscript->getKey(),
            'user_id' => $context['user']->getKey(),
            'category' => SubmissionFileCategory::ABSTRACT_FILES,
        ]);

        $submissionFile->forceFill([
            'created_at' => $uploadedAt,
            'updated_at' => $uploadedAt,
        ])->save();

        Livewire::test(AbstractFiles::class, ['submission' => $submission->refresh()])
            ->assertSee('Uploaded At')
            ->assertSee('15 January 2026 09:30');
    }

    public function test_submission_file_edit_action_can_change_file_type(): void
    {
        $context = $this->makeSubmissionContext();
        $submission = $context['submission'];

        $abstract = SubmissionFileType::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
            'name' => 'Abstract',
            'required' => true,
        ]);

        $poster = SubmissionFileType::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $context['scheduledConference']->getKey(),
            'name' => 'Poster',
            'required' => true,
        ]);

        $this->actingAs($context['user']);
        Gate::before(fn () => true);

        $media = $submission->addMedia(resource_path('assets/sample.pdf'))
            ->preservingOriginal()
            ->toMediaCollection(SubmissionFileCategory::ABSTRACT_FILES, 'private-files');

        $submissionFile = SubmissionFile::query()->create([
            'submission_id' => $submission->getKey(),
            'media_id' => $media->getKey(),
            'submission_file_type_id' => $abstract->getKey(),
            'user_id' => $context['user']->getKey(),
            'category' => SubmissionFileCategory::ABSTRACT_FILES,
        ]);

        Livewire::test(AbstractFiles::class, ['submission' => $submission])
            ->callTableAction('rename', $submissionFile, data: [
                'name' => 'renamed-submission-file',
                'type' => $poster->getKey(),
            ])
            ->assertDispatched('refreshLivewire');

        $this->assertDatabaseHas('submission_files', [
            'id' => $submissionFile->getKey(),
            'media_id' => $media->getKey(),
            'submission_file_type_id' => $poster->getKey(),
        ]);
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
