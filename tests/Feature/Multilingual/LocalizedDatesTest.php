<?php

namespace Tests\Feature\Multilingual;

use App\Constants\SubmissionFileCategory;
use App\Frontend\Conference\Pages\ProceedingDetail;
use App\Mail\Templates\ReviewerInvitationMail;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Review;
use App\Models\ScheduledConference;
use App\Models\SubmissionFile;
use App\Models\SubmissionFileType;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\AbstractFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class LocalizedDatesTest extends TestCase
{
    use InteractsWithContentLanguages;
    use MakesPapers;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableLanguages(['en', 'fr']);

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);

        $this->author = $this->makeAuthor();
    }

    public function test_public_paper_and_proceeding_pages_show_publication_dates_in_the_visitor_language(): void
    {
        $proceeding = $this->makeProceeding();
        $proceeding->update(['published_at' => Carbon::parse('2026-10-22')]);

        $paper = $this->makePaper($this->author, 'en', [
            'title' => ['en' => 'Coral reefs'],
        ], SubmissionStage::Proceeding, SubmissionStatus::Published, $proceeding);
        $paper->update(['published_at' => Carbon::parse('2026-10-22')]);

        $proceedingUrl = route(ProceedingDetail::getRouteName('conference'), [
            'conference' => $this->conference->path,
            'proceeding' => $proceeding->getKey(),
        ]);

        $this->visitPaper($this->conference, $paper, 'fr')
            ->assertSee('22 octobre 2026')
            ->assertDontSee('22 October 2026');

        $this->withoutVite()->withSession(['locale' => 'fr'])->get($proceedingUrl)
            ->assertOk()
            ->assertSee('22 octobre 2026')
            ->assertDontSee('22 October 2026');

        $this->visitPaper($this->conference, $paper, 'en')
            ->assertSee('22 October 2026');
    }

    public function test_submission_file_table_shows_the_upload_date_in_the_interface_language(): void
    {
        $submission = $this->makePaper($this->author, 'en', ['title' => ['en' => 'Coral reefs']]);
        $type = SubmissionFileType::withoutGlobalScopes()->create([
            'scheduled_conference_id' => $this->scheduledConference->getKey(),
            'name' => 'Manuscript',
        ]);
        $media = $submission->addMedia(resource_path('assets/sample.pdf'))
            ->preservingOriginal()
            ->toMediaCollection(SubmissionFileCategory::ABSTRACT_FILES, 'private-files');
        $file = SubmissionFile::query()->create([
            'submission_id' => $submission->getKey(),
            'media_id' => $media->getKey(),
            'submission_file_type_id' => $type->getKey(),
            'user_id' => $this->author->getKey(),
            'category' => SubmissionFileCategory::ABSTRACT_FILES,
        ]);
        $file->forceFill(['created_at' => Carbon::parse('2026-10-22 09:30')])->save();

        $this->actingAs($this->author);
        Gate::before(fn () => true);
        App::setLocale('fr');

        Livewire::test(AbstractFiles::class, ['submission' => $submission])
            ->assertSee('22 octobre 2026')
            ->assertDontSee('22 October 2026');
    }

    public function test_reviewer_invitation_deadlines_follow_the_recipient_language(): void
    {
        Route::get('/test/submissions/{record}/reviewer-invitation', fn () => null)
            ->name('filament.conference.resources.submissions.reviewer-invitation');
        Route::getRoutes()->refreshNameLookups();

        $submission = $this->makePaper($this->author, 'en', ['title' => ['en' => 'Coral reefs']]);
        $reviewer = User::query()->create([
            'given_name' => 'Camille',
            'family_name' => 'Martin',
            'email' => 'reviewer@example.test',
            'password' => 'password123456',
            'locale' => 'fr',
        ]);
        $review = Review::query()->create([
            'submission_id' => $submission->getKey(),
            'user_id' => $reviewer->getKey(),
        ]);
        $review->setManyMeta([
            'response_due_date' => '22 October 2026',
            'review_due_date' => '19 November 2026',
        ]);

        App::setLocale('en');

        $mail = new ReviewerInvitationMail($review->refresh());

        $french = $mail->locale('fr')->render();
        $this->assertStringContainsString('22 octobre 2026', $french);
        $this->assertStringContainsString('19 novembre 2026', $french);
        $this->assertStringNotContainsString('October', $french);

        $english = (new ReviewerInvitationMail($review))->locale('en')->render();
        $this->assertStringContainsString('22 October 2026', $english);
        $this->assertSame('en', app()->getLocale());
    }
}
