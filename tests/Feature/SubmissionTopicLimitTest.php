<?php

namespace Tests\Feature;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Models\Conference;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Topic;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Submissions\Forms\Detail as SubmissionDetailForm;
use App\Panel\ScheduledConference\Livewire\SubmissionSetting;
use App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Steps\DetailStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SubmissionTopicLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_setting_shows_topic_selection_limit_option(): void
    {
        $this->makeSubmissionContext();

        Livewire::test(SubmissionSetting::class)
            ->assertSee('Maximum topics per submission');
    }

    public function test_submission_setting_saves_topic_selection_limit(): void
    {
        $context = $this->makeSubmissionContext();

        Livewire::test(SubmissionSetting::class)
            ->set('formData.meta.submission_topic_selection_limit', 4)
            ->call('mountFormComponentAction', 'formData.saveAction', 'save');

        $this->assertSame(
            4,
            $context['scheduledConference']->refresh()->getSubmissionTopicSelectionLimit()
        );
    }

    public function test_submission_detail_forms_show_topic_selection_limit_helper(): void
    {
        $context = $this->makeSubmissionContext(topicLimit: 2);
        $this->makeTopics($context['scheduledConference'], 3);

        $this->actingAs($context['user']);

        Livewire::test(DetailStep::class, ['record' => $context['submission']])
            ->assertSee('Select up to 2 topics.');

        Livewire::test(SubmissionDetailForm::class, ['submission' => $context['submission']])
            ->assertSee('Select up to 2 topics.');
    }

    public function test_submission_detail_forms_render_without_limit_for_existing_conferences(): void
    {
        $context = $this->makeSubmissionContext();
        $this->makeTopics($context['scheduledConference'], 3);

        $this->actingAs($context['user']);

        Livewire::test(DetailStep::class, ['record' => $context['submission']])
            ->assertDontSee('Select up to');

        Livewire::test(SubmissionDetailForm::class, ['submission' => $context['submission']])
            ->assertDontSee('Select up to');
    }

    public function test_submission_update_rejects_topics_above_configured_limit(): void
    {
        $context = $this->makeSubmissionContext(topicLimit: 2);
        $topics = $this->makeTopics($context['scheduledConference'], 3);

        $this->actingAs($context['user']);

        try {
            SubmissionUpdateAction::run([
                'topics' => $topics->pluck('id')->all(),
            ], $context['submission']);

            $this->fail('Expected a validation exception when topic selection exceeds the configured limit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('topics', $exception->errors());
        }

        $this->assertSame([], $context['submission']->topics()->pluck('topics.id')->all());
    }

    public function test_submission_update_syncs_wizard_topic_field_within_configured_limit(): void
    {
        $context = $this->makeSubmissionContext(topicLimit: 2);
        $topics = $this->makeTopics($context['scheduledConference'], 2);

        $this->actingAs($context['user']);

        SubmissionUpdateAction::run([
            'topic' => $topics->pluck('id')->all(),
        ], $context['submission']);

        $this->assertEqualsCanonicalizing(
            $topics->pluck('id')->all(),
            $context['submission']->refresh()->topics()->pluck('topics.id')->all()
        );
    }

    protected function makeSubmissionContext(?int $topicLimit = null): array
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

        if ($topicLimit !== null) {
            $scheduledConference->setMeta('submission_topic_selection_limit', $topicLimit);
        }

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

    protected function makeTopics(ScheduledConference $scheduledConference, int $count)
    {
        return Topic::factory()
            ->count($count)
            ->create([
                'conference_id' => $scheduledConference->conference_id,
                'scheduled_conference_id' => $scheduledConference->getKey(),
            ]);
    }
}
