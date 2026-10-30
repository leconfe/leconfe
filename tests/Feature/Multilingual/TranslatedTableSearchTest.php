<?php

namespace Tests\Feature\Multilingual;

use App\Constants\ReviewerStatus;
use App\Models\Announcement;
use App\Models\AuthorRole;
use App\Models\CommitteeRole;
use App\Models\Conference;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Proceeding;
use App\Models\Review;
use App\Models\ReviewFormItem;
use App\Models\ScheduledConference;
use App\Models\SpeakerRole;
use App\Models\Stakeholder;
use App\Models\StakeholderLevel;
use App\Models\StaticPage;
use App\Models\SubmissionFileType;
use App\Models\SubmissionReviewRound;
use App\Models\Topic;
use App\Models\User;
use App\Panel\Administration\Livewire\SponsorLevelTable;
use App\Panel\Administration\Livewire\SponsorTable;
use App\Panel\Administration\Resources\StaticPageResource\Pages\ListStaticPages;
use App\Panel\Conference\Livewire\ProceedingDOI;
use App\Panel\Conference\Livewire\SubmissionDOI;
use App\Panel\Conference\Resources\ProceedingResource\Pages\ManageProceedings;
use App\Panel\Conference\Resources\ProceedingResource\Pages\ViewProceeding;
use App\Panel\ScheduledConference\Livewire\AuthorRoleTable;
use App\Panel\ScheduledConference\Livewire\CommitteeRoleTable;
use App\Panel\ScheduledConference\Livewire\ReviewFormTable;
use App\Panel\ScheduledConference\Livewire\SpeakerRoleTable;
use App\Panel\ScheduledConference\Livewire\SubmissionFileTypeTable;
use App\Panel\ScheduledConference\Livewire\TopicTable;
use App\Panel\ScheduledConference\Pages\ReviewResult;
use App\Panel\ScheduledConference\Resources\AnnouncementResource\Pages\ListAnnouncements;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class TranslatedTableSearchTest extends TestCase
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

        Gate::before(fn () => true);

        $this->author = $this->makeAuthor();
        $this->actingAs($this->author);

        $this->enableLanguages(['en', 'fr']);
        app()->setLocale('fr');
    }

    public function test_proceedings_are_found_by_their_translated_title(): void
    {
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_CONFERENCE));

        $proceeding = $this->makeFrenchProceeding('Ocean science proceedings', 'Biodiversité maritime');

        $otherConference = $this->makeConference('Other Conference', 'other');
        $this->useConference($otherConference);
        $otherProceeding = $this->makeFrenchProceeding('Coastal proceedings', 'Biodiversité côtière');
        $this->useConference($this->conference, $this->scheduledConference);
        app()->scopeCurrentConference();

        Livewire::test(ManageProceedings::class)
            ->set('activeTab', 'back')
            ->assertSee('Biodiversité maritime')
            ->searchTable('Biodiversité')
            ->assertCanSeeTableRecords([$proceeding])
            ->assertCanNotSeeTableRecords([$otherProceeding])
            ->searchTable('biodiversité')
            ->assertCanSeeTableRecords([$proceeding])
            ->searchTable('Ocean')
            ->assertCanSeeTableRecords([$proceeding])
            ->searchTable('Coastal')
            ->assertCanNotSeeTableRecords([$proceeding, $otherProceeding]);
    }

    public function test_conference_tables_are_searched_by_translated_names(): void
    {
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_CONFERENCE));

        $proceeding = $this->translate($this->makeProceeding('Harbour logistics'), 'title');
        $this->assertSearchFindsTranslation(ProceedingDOI::class, $proceeding);

        $paper = $this->makePaper($this->author, 'en', [
            'title' => ['en' => 'Harbour logistics', 'fr' => 'Logistique portuaire'],
        ], SubmissionStage::Proceeding, SubmissionStatus::Published, $this->makeProceeding('Volume one'));

        $this->assertSearchFindsTranslation(SubmissionDOI::class, $paper);
        $this->assertSearchFindsTranslation(ViewProceeding::class, $paper, ['record' => $paper->proceeding_id]);
    }

    public function test_scheduled_conference_tables_are_searched_by_translated_names(): void
    {
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));

        $tables = [
            ListAnnouncements::class => fn () => $this->translate(Announcement::query()->create(['title' => 'Harbour logistics']), 'title'),
            CommitteeRoleTable::class => fn () => $this->translate(CommitteeRole::query()->create(['name' => 'Harbour logistics']), 'name'),
            SpeakerRoleTable::class => fn () => $this->translate(SpeakerRole::query()->create(['name' => 'Harbour logistics']), 'name'),
            AuthorRoleTable::class => fn () => $this->translate(AuthorRole::query()->create(['name' => 'Harbour logistics']), 'name'),
            SubmissionFileTypeTable::class => fn () => $this->translate(SubmissionFileType::query()->create(['name' => 'Harbour logistics']), 'name'),
            ReviewFormTable::class => fn () => $this->translate(ReviewFormItem::query()->create(['type' => ReviewFormItem::TYPE_TEXT, 'label' => 'Harbour logistics']), 'label'),
            TopicTable::class => fn () => $this->translate(Topic::query()->create(['name' => 'Harbour logistics']), 'name'),
            SponsorLevelTable::class => fn () => $this->translate(StakeholderLevel::query()->create(['type' => StakeholderLevel::TYPE_SPONSOR, 'name' => 'Harbour logistics']), 'name'),
        ];

        foreach ($tables as $component => $makeRecord) {
            $this->assertSearchFindsTranslation($component, $makeRecord());
        }

        $level = $this->translate(StakeholderLevel::query()->create(['type' => StakeholderLevel::TYPE_SPONSOR, 'name' => 'Harbour logistics sponsors']), 'name');
        $sponsor = Stakeholder::query()->create(['type' => Stakeholder::TYPE_SPONSOR, 'level_id' => $level->getKey(), 'name' => 'Acme']);
        $this->assertSearchFindsTranslation(SponsorTable::class, $sponsor);

        $paper = $this->makePaper($this->author, 'en', [
            'title' => ['en' => 'Harbour logistics', 'fr' => 'Logistique portuaire'],
        ], SubmissionStage::PeerReview, SubmissionStatus::OnReview);
        $round = SubmissionReviewRound::query()->create(['submission_id' => $paper->getKey(), 'round_number' => 1, 'status' => SubmissionReviewRound::STATUS_OPEN]);
        Review::query()->create([
            'submission_id' => $paper->getKey(),
            'review_round_id' => $round->getKey(),
            'user_id' => $this->makeAuthor('reviewer@example.test')->getKey(),
            'status' => ReviewerStatus::ACCEPTED,
            'score' => 4,
            'date_completed' => now(),
        ]);
        $this->assertSearchFindsTranslation(ReviewResult::class, $paper);
    }

    public function test_static_pages_are_searched_by_translated_titles(): void
    {
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_ADMINISTRATION));

        $page = $this->translate(StaticPage::query()->create(['title' => 'Harbour logistics', 'slug' => 'harbour']), 'title');

        $this->assertSearchFindsTranslation(ListStaticPages::class, $page);
    }

    protected function translate(Model $record, string $key): Model
    {
        $record->setTranslation($key, 'fr', 'Logistique portuaire');

        return $record;
    }

    protected function assertSearchFindsTranslation(string $component, Model $record, array $parameters = []): void
    {
        Livewire::test($component, $parameters)
            ->searchTable('portuaire')
            ->assertCanSeeTableRecords([$record])
            ->searchTable('Harbour')
            ->assertCanSeeTableRecords([$record])
            ->searchTable('Volcanology')
            ->assertCanNotSeeTableRecords([$record]);
    }

    protected function makeFrenchProceeding(string $title, string $frenchTitle): Proceeding
    {
        $proceeding = $this->makeProceeding($title);
        $proceeding->setTranslation('title', 'fr', $frenchTitle);

        return $proceeding;
    }
}
