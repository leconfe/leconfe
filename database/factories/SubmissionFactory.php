<?php

namespace Database\Factories;

use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\SubmissionFileType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Submission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Submission $submission) {
            $submission->setManyMeta([
                'title' => fake()->sentence(),
                'keywords' => fake()->words(5),
                'abstract' => fake()->paragraph(),
            ]);
            $submission->refresh();

            $states = [
                null,
                SubmissionStatus::Incomplete,
                SubmissionStatus::Queued,
            ];

            $state = Arr::random($states);

            if ($state) {
                $submission->state()->fulfill();
            }

            if (in_array($state, [SubmissionStatus::Incomplete, SubmissionStatus::Queued])) {
                $conferenceEditorRole = Role::withoutGlobalScopes()
                    ->where('scheduled_conference_id', $submission->scheduled_conference_id)
                    ->whereIn('name', [UserRole::ScheduledConferenceEditor, UserRole::TrackEditor])->first();
                $userConferenceEditor = $conferenceEditorRole->users->first();

                $submission->participants()->create([
                    'user_id' => $userConferenceEditor->getKey(),
                    'role_id' => $conferenceEditorRole->getKey(),
                ]);
            }

            if (in_array($state, [SubmissionStatus::Queued])) {
                $media = $submission->addMedia(resource_path('assets/sample.pdf'))
                    ->preservingOriginal()
                    ->toMediaCollection('abstract-files', 'private-files');

                $submissionFileType = SubmissionFileType::withoutGlobalScopes()->where('scheduled_conference_id', $submission->scheduled_conference_id)->first();

                SubmissionFile::create([
                    'submission_id' => $submission->id,
                    'submission_file_type_id' => $submissionFileType->id,
                    'media_id' => $media->id,
                    'user_id' => $submission->user_id,
                    'category' => 'abstract-files',
                ]);

                $submission->state()->sendForReview();
            }
        });
    }
}
