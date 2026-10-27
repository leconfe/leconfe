<?php

namespace Tests\Feature\Multilingual;

use App\Frontend\Conference\Pages\ProceedingDetail;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\NavigationMenuItem;
use App\Models\Proceeding;
use App\Models\ScheduledConference;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class PublicInterfaceLabelsTest extends TestCase
{
    use InteractsWithContentLanguages;
    use MakesPapers;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected User $author;

    protected Proceeding $proceeding;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableLanguages(['en', 'fr']);

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);

        $this->author = $this->makeAuthor();
        $this->proceeding = $this->makeProceeding();
    }

    public function test_paper_page_breadcrumbs_and_preview_notice_follow_the_visitor_language(): void
    {
        $paper = $this->makePaper($this->author, 'en', [
            'title' => ['en' => 'Coral reefs'],
        ], SubmissionStage::Proceeding, SubmissionStatus::Published, $this->proceeding);

        $response = $this->visitPaper($this->conference, $paper, 'fr');
        $this->assertSame(['Accueil', 'Actes', 'Vol. 1 No. 2 (2026): Proceedings 2026', 'Article'], $this->breadcrumbs($response));

        $draft = $this->makePaper($this->author, 'en', [
            'title' => ['en' => 'Kelp forests'],
        ], SubmissionStage::Editing, SubmissionStatus::Editing, $this->proceeding);

        $this->actingAs($this->author);
        Gate::before(fn () => true);

        $this->visitPaper($this->conference, $draft, 'fr')
            ->assertSee('Aperçu.')
            ->assertSee('Cet article n’est pas encore publié. Ceci n’est qu’un aperçu de l’article.')
            ->assertDontSee('This paper is not published yet');
    }

    public function test_proceeding_page_labels_follow_the_visitor_language(): void
    {
        $response = $this->withoutVite()
            ->withSession(['locale' => 'fr'])
            ->get(route(ProceedingDetail::getRouteName('conference'), [
                'conference' => $this->conference->path,
                'proceeding' => $this->proceeding->getKey(),
            ]))
            ->assertOk()
            ->assertSee('Publié :')
            ->assertDontSee('Published:');

        $this->assertSame(['Accueil', 'Actes', 'Vol. 1 No. 2 (2026): Proceedings 2026'], $this->breadcrumbs($response));
    }

    public function test_missing_public_page_error_follows_the_visitor_language(): void
    {
        $this->withoutVite()
            ->withSession(['locale' => 'fr'])
            ->get(route(ProceedingDetail::getRouteName('conference'), [
                'conference' => $this->conference->path,
                'proceeding' => $this->proceeding->getKey() + 1,
            ]))
            ->assertNotFound()
            ->assertSee('L’adresse que vous cherchez est introuvable')
            ->assertDontSee('The URL you are looking for was not found');
    }

    public function test_built_in_account_menu_follows_the_visitor_language_and_custom_labels_stay(): void
    {
        $paper = $this->makePaper($this->author, 'en', [
            'title' => ['en' => 'Coral reefs'],
        ], SubmissionStage::Proceeding, SubmissionStatus::Published, $this->proceeding);

        $this->accountItems('profile')->each->update(['label' => 'My account']);
        $this->accountItems('dashboard')->each->setTranslation('label', 'fr', 'Espace auteur');

        $this->actingAs($this->author);

        $this->visitPaper($this->conference, $paper, 'fr')
            ->assertSee('Déconnexion')
            ->assertDontSee('Logout')
            ->assertSee('My account')
            ->assertSee('Espace auteur')
            ->assertSee('Ayu Lestari');

        $this->visitPaper($this->conference, $paper, 'en')
            ->assertSee('Logout')
            ->assertSee('My account')
            ->assertDontSee('Espace auteur');
    }

    public function test_login_page_back_link_follows_the_visitor_language(): void
    {
        $this->withoutVite()
            ->withSession(['locale' => 'fr'])
            ->get(route('livewirePageGroup.scheduledConference.pages.login', [
                'conference' => $this->conference->path,
                'serie' => $this->scheduledConference->path,
            ]))
            ->assertOk()
            ->assertSee('Retour à l’accueil')
            ->assertDontSee('Back to home');
    }

    /**
     * The built-in items of a type in every user account menu.
     */
    protected function accountItems(string $type): Collection
    {
        return NavigationMenuItem::query()
            ->where('type', $type)
            ->whereNotNull('parent_id')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    protected function breadcrumbs(TestResponse $response): array
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());

        $items = (new DOMXPath($document))->query('//div[contains(@class, "breadcrumbs")]//li');

        return collect(iterator_to_array($items))
            ->map(fn ($item) => trim(preg_replace('/\s+/', ' ', $item->textContent)))
            ->all();
    }
}
