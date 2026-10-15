<?php

namespace Tests\Feature\Multilingual;

use App\Actions\Submissions\StartSubmissionReviewRoundAction;
use App\Mail\Templates\DeclineAbstractMail;
use App\Mail\Templates\ReviewerInvitationMail;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\MailTemplate;
use App\Models\Review;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Submissions\CallforAbstract;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ReviewerList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class PrefilledEmailTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected User $editor;

    protected Role $editorRole;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['', '/review', '/reviewer-invitation'] as $suffix) {
            Route::get("/test/submissions/{record}{$suffix}", fn () => null)
                ->name('filament.conference.resources.submissions.'.(ltrim($suffix, '/') ?: 'view'));
        }
        Route::getRoutes()->refreshNameLookups();

        app()->getSite();
        $this->enableLanguages(['en', 'id']);

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);

        $this->editor = $this->makeUser('editor@example.test', 'en');
        $this->editorRole = $this->role(UserRole::ScheduledConferenceEditor);
        $this->editor->assignRole($this->editorRole);

        Mail::fake();
    }

    public function test_decline_email_is_prefilled_in_the_authors_language_and_sent_as_edited(): void
    {
        $this->translatedTemplate(
            DeclineAbstractMail::class,
            ['Abstract declined', '<p>We are sorry.</p>'],
            ['Abstrak ditolak', '<p>Mohon maaf.</p>'],
        );

        $submission = $this->makeSubmission($this->makeUser('penulis@example.test', 'id'), SubmissionStage::CallforAbstract, SubmissionStatus::Queued);

        $this->actingAs($this->editor);
        App::setLocale('en');

        Livewire::test(CallforAbstract::class, ['submission' => $submission])
            ->mountAction('decline')
            ->assertActionDataSet([
                'subject' => 'Abstrak ditolak',
                'message' => '<p>Mohon maaf.</p>',
            ])
            ->setActionData([
                'subject' => 'Abstrak ditolak (diubah)',
                'message' => '<p>Teks yang ditulis editor.</p>',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        Mail::assertQueued(DeclineAbstractMail::class, fn (DeclineAbstractMail $mail) => $mail->locale === 'id'
            && $mail->customizedSubject === 'Abstrak ditolak (diubah)'
            && $mail->customizedContent === '<p>Teks yang ditulis editor.</p>');
    }

    public function test_decline_email_is_prefilled_in_english_for_an_english_author_while_the_editor_uses_indonesian(): void
    {
        $this->translatedTemplate(
            DeclineAbstractMail::class,
            ['Abstract declined', '<p>We are sorry.</p>'],
            ['Abstrak ditolak', '<p>Mohon maaf.</p>'],
        );

        $submission = $this->makeSubmission($this->makeUser('author@example.test', 'en'), SubmissionStage::CallforAbstract, SubmissionStatus::Queued);

        $this->actingAs($this->editor);
        App::setLocale('id');

        Livewire::test(CallforAbstract::class, ['submission' => $submission])
            ->mountAction('decline')
            ->assertActionDataSet([
                'subject' => 'Abstract declined',
                'message' => '<p>We are sorry.</p>',
            ]);
    }

    public function test_reviewer_invitation_is_refilled_in_the_chosen_reviewers_language(): void
    {
        $this->translatedTemplate(
            ReviewerInvitationMail::class,
            ['Review invitation', '<p>Please review this paper.</p>'],
            ['Undangan menelaah', '<p>Mohon menelaah naskah ini.</p>'],
        );

        $reviewer = $this->makeReviewer('penelaah@example.test', 'id');
        $submission = $this->makeReviewableSubmission();

        $this->actingAs($this->editor);
        App::setLocale('en');

        Livewire::test(ReviewerList::class, ['record' => $submission])
            ->mountTableAction('add-reviewer')
            ->assertTableActionDataSet([
                'subject' => 'Review invitation',
                'message' => '<p>Please review this paper.</p>',
            ])
            ->setTableActionData(['user_id' => $reviewer->getKey()])
            ->assertTableActionDataSet([
                'subject' => 'Undangan menelaah',
                'message' => '<p>Mohon menelaah naskah ini.</p>',
            ])
            ->setTableActionData([
                'meta' => ['review_mode' => Review::MODE_OPEN],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        Mail::assertQueued(ReviewerInvitationMail::class, fn (ReviewerInvitationMail $mail) => $mail->locale === 'id'
            && $mail->hasTo('penelaah@example.test')
            && $mail->customizedSubject === 'Undangan menelaah'
            && $mail->customizedContent === '<p>Mohon menelaah naskah ini.</p>');
    }

    public function test_reviewer_invitation_keeps_text_edited_by_the_sender(): void
    {
        $this->translatedTemplate(
            ReviewerInvitationMail::class,
            ['Review invitation', '<p>Please review this paper.</p>'],
            ['Undangan menelaah', '<p>Mohon menelaah naskah ini.</p>'],
        );

        $reviewer = $this->makeReviewer('penelaah@example.test', 'id');
        $submission = $this->makeReviewableSubmission();

        $this->actingAs($this->editor);

        Livewire::test(ReviewerList::class, ['record' => $submission])
            ->mountTableAction('add-reviewer')
            ->setTableActionData(['subject' => 'My own subject'])
            ->setTableActionData(['user_id' => $reviewer->getKey()])
            ->assertTableActionDataSet([
                'subject' => 'My own subject',
                'message' => '<p>Mohon menelaah naskah ini.</p>',
            ])
            ->setTableActionData([
                'message' => '<p>My own message</p>',
                'meta' => ['review_mode' => Review::MODE_OPEN],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        Mail::assertQueued(ReviewerInvitationMail::class, fn (ReviewerInvitationMail $mail) => $mail->customizedSubject === 'My own subject'
            && $mail->customizedContent === '<p>My own message</p>');
    }

    /**
     * @param  array{0: string, 1: string}  $english
     * @param  array{0: string, 1: string}  $indonesian
     */
    protected function translatedTemplate(string $mailable, array $english, array $indonesian): MailTemplate
    {
        $template = MailTemplate::query()->create([
            'conference_id' => $this->conference->getKey(),
            'mailable' => $mailable,
            'subject' => $english[0],
            'html_template' => $english[1],
        ]);

        $template->setTranslation('subject', 'id', $indonesian[0]);
        $template->setTranslation('html_template', 'id', $indonesian[1]);

        return $template;
    }

    protected function makeUser(string $email, ?string $locale): User
    {
        return User::query()->create([
            'given_name' => 'Test',
            'family_name' => 'User',
            'email' => $email,
            'password' => 'password123456',
            'locale' => $locale,
        ]);
    }

    protected function makeReviewer(string $email, string $locale): User
    {
        $reviewer = $this->makeUser($email, $locale);
        $reviewer->assignRole($this->role(UserRole::Reviewer));

        return $reviewer;
    }

    protected function role(UserRole $role): Role
    {
        return Role::withoutGlobalScopes()
            ->where('name', $role->value)
            ->where('conference_id', $this->conference->getKey())
            ->where('scheduled_conference_id', $this->scheduledConference->getKey())
            ->firstOrFail();
    }

    protected function makeSubmission(User $author, SubmissionStage $stage, SubmissionStatus $status): Submission
    {
        $track = Track::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'title' => 'Track',
            'abbreviation' => 'TRK',
            'is_active' => true,
        ]);

        $submission = Submission::withoutGlobalScopes()->forceCreate([
            'user_id' => $author->getKey(),
            'conference_id' => $this->conference->getKey(),
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'track_id' => $track->getKey(),
            'stage' => $stage,
            'status' => $status,
        ]);
        $submission->setMeta('title', 'Rice terraces');

        $submission->participants()->create([
            'user_id' => $this->editor->getKey(),
            'role_id' => $this->editorRole->getKey(),
        ]);

        return $submission;
    }

    protected function makeReviewableSubmission(): Submission
    {
        $submission = $this->makeSubmission(
            $this->makeUser('author@example.test', 'en'),
            SubmissionStage::PeerReview,
            SubmissionStatus::OnReview,
        );

        StartSubmissionReviewRoundAction::run($submission, [], $this->editor);

        return $submission->refresh();
    }
}
