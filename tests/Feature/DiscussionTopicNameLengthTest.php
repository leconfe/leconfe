<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\DiscussionTopic as DiscussionTopicModel;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Notifications\NewDiscussionTopic;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\Discussions\DiscussionTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class DiscussionTopicNameLengthTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\DataProvider('acceptedTopicNames')]
    public function test_topic_can_be_created_with_name_up_to_column_length(string $name): void
    {
        Notification::fake();
        $context = $this->makeDiscussionContext();

        Livewire::test(DiscussionTopic::class, [
            'submission' => $context['submission'],
            'stage' => SubmissionStage::Editing,
        ])
            ->callTableAction('create-topic', data: [
                'name' => $name,
                'user_id' => [$context['editor']->getKey(), $context['author']->getKey()],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame($name, $context['submission']->discussionTopics()->sole()->name);
    }

    public static function acceptedTopicNames(): array
    {
        return [
            'short name' => ['Question about the camera-ready file'],
            '191 characters' => [str_repeat('a', 191)],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedTopicNames')]
    public function test_topic_creation_rejects_name_longer_than_column_length(string $name): void
    {
        Notification::fake();
        $context = $this->makeDiscussionContext();

        Livewire::test(DiscussionTopic::class, [
            'submission' => $context['submission'],
            'stage' => SubmissionStage::Editing,
        ])
            ->callTableAction('create-topic', data: [
                'name' => $name,
                'user_id' => [$context['editor']->getKey(), $context['author']->getKey()],
            ])
            ->assertHasTableActionErrors(['name' => 'max']);

        $this->assertSame(0, $context['submission']->discussionTopics()->count());
        Notification::assertNothingSentTo($context['author'], NewDiscussionTopic::class);
    }

    public static function rejectedTopicNames(): array
    {
        return [
            '192 characters' => [str_repeat('a', 192)],
            'pasted message' => [str_repeat("Mohon revisi bagian metodologi dan perbaiki daftar pustaka.\n", 10)],
        ];
    }

    public function test_topic_update_rejects_name_longer_than_column_length(): void
    {
        $context = $this->makeDiscussionContext();

        $topic = $context['submission']->discussionTopics()->create([
            'name' => 'Original topic',
            'open' => true,
            'stage' => SubmissionStage::Editing,
            'user_id' => $context['editor']->getKey(),
        ]);
        $topic->participants()->create(['user_id' => $context['editor']->getKey()]);
        $topic->participants()->create(['user_id' => $context['author']->getKey()]);

        Livewire::test(DiscussionTopic::class, [
            'submission' => $context['submission'],
            'stage' => SubmissionStage::Editing,
        ])
            ->callTableAction('update-topic', $topic, data: [
                'name' => str_repeat('a', 192),
            ])
            ->assertHasTableActionErrors(['name' => 'max']);

        $this->assertSame('Original topic', $topic->fresh()->name);
    }

    protected function makeDiscussionContext(): array
    {
        $conference = Conference::query()->create([
            'name' => 'Conference '.uniqid(),
            'path' => 'conf-'.uniqid(),
        ]);

        $scheduledConference = ScheduledConference::withoutGlobalScopes()->create([
            'conference_id' => $conference->getKey(),
            'title' => 'SC '.uniqid(),
            'path' => 'sc-'.uniqid(),
            'date_start' => now()->toDateString(),
            'date_end' => now()->addDays(2)->toDateString(),
        ]);

        app()->setCurrentConferenceId($conference->getKey());
        app()->setCurrentScheduledConferenceId($scheduledConference->getKey());

        $track = Track::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'title' => 'Track '.uniqid(),
            'abbreviation' => 'TRK',
            'is_active' => true,
        ]);

        $author = User::query()->create([
            'given_name' => 'Author',
            'family_name' => 'Tester',
            'email' => 'author-'.uniqid().'@example.test',
            'password' => 'password123456',
        ]);

        $editor = User::query()->create([
            'given_name' => 'Editor',
            'family_name' => 'Tester',
            'email' => 'editor-'.uniqid().'@example.test',
            'password' => 'password123456',
        ]);

        $submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $author->getKey(),
            'conference_id' => $conference->getKey(),
            'scheduled_conference_id' => $scheduledConference->getKey(),
            'track_id' => $track->getKey(),
            'stage' => SubmissionStage::Editing,
            'status' => SubmissionStatus::Editing,
            'revision_required' => false,
        ]);

        $submission->setMeta('title', 'Submission '.uniqid());

        $editorRole = $this->scheduledConferenceRole(UserRole::ScheduledConferenceEditor);
        foreach (['DiscussionTopic:create', 'DiscussionTopic:update'] as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $editorRole->givePermissionTo(['DiscussionTopic:create', 'DiscussionTopic:update']);
        $editor->assignRole($editorRole);
        $submission->participants()->create([
            'user_id' => $editor->getKey(),
            'role_id' => $editorRole->getKey(),
        ]);

        $authorRole = $this->scheduledConferenceRole(UserRole::Author);
        $author->assignRole($authorRole);
        $submission->participants()->create([
            'user_id' => $author->getKey(),
            'role_id' => $authorRole->getKey(),
        ]);

        $this->actingAs($editor);

        return [
            'submission' => $submission,
            'author' => $author,
            'editor' => $editor,
        ];
    }

    protected function scheduledConferenceRole(UserRole $role): Role
    {
        return Role::withoutGlobalScopes()
            ->where('name', $role->value)
            ->where('conference_id', app()->getCurrentConferenceId())
            ->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId())
            ->firstOrFail();
    }
}
