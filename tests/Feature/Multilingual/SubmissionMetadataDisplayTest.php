<?php

namespace Tests\Feature\Multilingual;

use App\Actions\Submissions\StartSubmissionReviewRoundAction;
use App\Constants\ReviewerStatus;
use App\Frontend\Conference\Pages\ProceedingDetail;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Proceeding;
use App\Models\Review;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\Submission;
use App\Models\User;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ManageSubmissions;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ReviewSubmissionPage;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\ViewSubmission;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class SubmissionMetadataDisplayTest extends TestCase
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

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);
        $this->conference->setMeta('primary_citation_format', 'apa');

        // Site default en; the paper is written in Indonesian.
        $this->enableLanguages(['en', 'id', 'fr']);

        $this->author = $this->makeAuthor();
    }

    public function test_paper_page_shows_the_current_language_and_falls_back_to_the_paper_language(): void
    {
        $paper = $this->makePublishedPaper($this->makeProceeding(), 'Terumbu karang', 'Coral reefs');

        $en = $this->body($this->visitPaper($this->conference, $paper, 'en'));
        $this->assertStringContainsString('Coral reefs</h1>', $en);
        $this->assertStringContainsString('<p>A study of coral reefs</p>', $en);
        $this->assertStringContainsString('reef', $en);
        // The citation stays in the paper language.
        $this->assertStringNotContainsString('Terumbu karang</h1>', $en);
        $this->assertStringNotContainsString('Kajian Terumbu karang', $en);

        $id = $this->body($this->visitPaper($this->conference, $paper, 'id'));
        $this->assertStringContainsString('Terumbu karang</h1>', $id);
        $this->assertStringContainsString('<p>Kajian Terumbu karang</p>', $id);
        $this->assertStringNotContainsString('Coral reefs', $id);

        // No French translation: the paper language is shown, not the site default.
        $fr = $this->body($this->visitPaper($this->conference, $paper, 'fr'));
        $this->assertStringContainsString('Terumbu karang</h1>', $fr);
        $this->assertStringContainsString('lang="id"', $fr);
        $this->assertStringContainsString('<p>Kajian Terumbu karang</p>', $fr);
        $this->assertStringContainsString('karang', $fr);
        $this->assertStringNotContainsString('Coral reefs', $fr);
        $this->assertStringNotContainsString('A study of coral reefs', $fr);
    }

    public function test_proceeding_listing_shows_the_current_language_and_falls_back_to_the_paper_language(): void
    {
        $proceeding = $this->makeProceeding();
        $this->makePublishedPaper($proceeding, 'Terumbu karang', 'Coral reefs');

        $this->visitProceeding($proceeding, 'en')
            ->assertSee('Coral reefs')
            ->assertSee('Field notes')
            ->assertDontSee('Terumbu karang');

        $this->visitProceeding($proceeding, 'fr')
            ->assertSee('Terumbu karang')
            ->assertSee('Catatan lapangan')
            ->assertDontSee('Coral reefs')
            ->assertDontSee('Field notes');
    }

    public function test_proceeding_listing_translation_queries_do_not_grow_with_the_number_of_papers(): void
    {
        $proceeding = $this->makeProceeding();

        foreach (range(1, 2) as $number) {
            $this->makePublishedPaper($proceeding, "Makalah {$number}", "Paper {$number}");
        }

        $few = $this->countSubmissionTranslateQueries(fn () => $this->visitProceeding($proceeding, 'fr')->assertSee('Makalah 2'));

        foreach (range(3, 6) as $number) {
            $this->makePublishedPaper($proceeding, "Makalah {$number}", "Paper {$number}");
        }

        $many = $this->countSubmissionTranslateQueries(fn () => $this->visitProceeding($proceeding, 'fr')->assertSee('Makalah 6'));

        $this->assertGreaterThan(0, $few);
        $this->assertSame($few, $many);
    }

    public function test_panel_pages_show_the_current_language_and_fall_back_to_the_paper_language(): void
    {
        $this->usePanel();

        $submission = $this->makePaper($this->author, 'id', [
            'title' => ['id' => 'Terumbu karang', 'en' => 'Coral reefs'],
            'abstract' => ['id' => '<p>Kajian terumbu karang</p>', 'en' => '<p>A study of coral reefs</p>'],
            'keywords' => ['id' => ['karang'], 'en' => ['reef']],
        ], SubmissionStage::PeerReview, SubmissionStatus::OnReview);

        app()->setLocale('en');

        // The page also holds the metadata form with every language, so check the heading.
        Livewire::test(ViewSubmission::class, ['record' => $submission->getKey()])
            ->assertSeeHtml('<span class="text-xl ">Coral reefs</span>');

        app()->setLocale('fr');

        Livewire::test(ViewSubmission::class, ['record' => $submission->getKey()])
            ->assertSeeHtml('<span class="text-xl ">Terumbu karang</span>');

        $this->assertSame('Terumbu karang', ViewSubmission::getResource()::getRecordTitle($submission));

        $reviewer = $this->makeAuthor('reviewer@example.test');
        $round = StartSubmissionReviewRoundAction::run($submission, [], $this->author);
        Review::query()->create([
            'submission_id' => $submission->getKey(),
            'review_round_id' => $round->getKey(),
            'user_id' => $reviewer->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'date_confirmed' => now(),
        ])->setMeta('review_mode', Review::MODE_OPEN);

        $this->actingAs($reviewer);

        app()->setLocale('en');

        Livewire::test(ReviewSubmissionPage::class, ['record' => $submission])
            ->assertSee('Coral reefs')
            ->assertSee('A study of coral reefs')
            ->assertSee('reef')
            ->assertDontSee('Kajian terumbu karang');

        app()->setLocale('fr');

        Livewire::test(ReviewSubmissionPage::class, ['record' => $submission])
            ->assertSee('Terumbu karang')
            ->assertSee('Kajian terumbu karang')
            ->assertDontSee('Coral reefs')
            ->assertDontSee('A study of coral reefs');
    }

    public function test_panel_search_finds_a_submission_by_a_translated_title(): void
    {
        $this->usePanel();

        $role = Role::withoutGlobalScopes()
            ->where('name', UserRole::Author->value)
            ->where('scheduled_conference_id', $this->scheduledConference->getKey())
            ->firstOrFail();

        $reefs = $this->makePaper($this->author, 'en', [
            'title' => ['en' => 'Coral reefs', 'id' => 'Terumbu karang'],
        ], SubmissionStage::CallforAbstract, SubmissionStatus::Queued);
        $forests = $this->makePaper($this->author, 'en', [
            'title' => ['en' => 'Mangrove forests', 'id' => 'Hutan bakau'],
        ], SubmissionStage::CallforAbstract, SubmissionStatus::Queued);

        foreach ([$reefs, $forests] as $submission) {
            $submission->participants()->create(['user_id' => $this->author->getKey(), 'role_id' => $role->getKey()]);
        }

        Livewire::test(ManageSubmissions::class)
            ->searchTable('Terumbu')
            ->assertCanSeeTableRecords([$reefs])
            ->assertCanNotSeeTableRecords([$forests])
            ->searchTable('Mangrove')
            ->assertCanSeeTableRecords([$forests])
            ->assertCanNotSeeTableRecords([$reefs])
            ->searchTable('Lestari')
            ->assertCanSeeTableRecords([$reefs, $forests]);
    }

    protected function makePublishedPaper(Proceeding $proceeding, string $idTitle, string $enTitle): Submission
    {
        return $this->makePaper($this->author, 'id', [
            'title' => ['id' => $idTitle, 'en' => $enTitle],
            'subtitle' => ['id' => 'Catatan lapangan', 'en' => 'Field notes'],
            'abstract' => ['id' => "<p>Kajian {$idTitle}</p>", 'en' => '<p>A study of coral reefs</p>'],
            'keywords' => ['id' => ['karang'], 'en' => ['reef']],
        ], SubmissionStage::Proceeding, SubmissionStatus::Published, $proceeding);
    }

    protected function visitProceeding(Proceeding $proceeding, string $locale): TestResponse
    {
        return $this->withoutVite()
            ->withSession(['locale' => $locale])
            ->get(route(ProceedingDetail::getRouteName('conference'), [
                'conference' => $this->conference->path,
                'proceeding' => $proceeding->getKey(),
            ]))
            ->assertOk();
    }

    /**
     * The page body, without the meta tags in the head that list every translation.
     */
    protected function body(TestResponse $response): string
    {
        return Str::after($response->getContent(), '<body');
    }

    protected function usePanel(): void
    {
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));

        // Permissions are covered elsewhere; these tests cover the rendered content.
        Gate::before(fn () => true);

        $this->actingAs($this->author);
    }

    protected function countSubmissionTranslateQueries(callable $callback): int
    {
        $count = 0;
        $morphClass = (new Submission)->getMorphClass();

        DB::listen(function (QueryExecuted $query) use (&$count, $morphClass) {
            if (preg_match('/\bfrom\s+["`]?translate["`]?/i', $query->sql) && in_array($morphClass, $query->bindings, true)) {
                $count++;
            }
        });

        $callback();

        app('events')->forget(QueryExecuted::class);

        return $count;
    }
}
