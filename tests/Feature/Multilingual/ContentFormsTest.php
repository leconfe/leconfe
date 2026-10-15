<?php

namespace Tests\Feature\Multilingual;

use App\Models\Announcement;
use App\Models\Conference;
use App\Models\NavigationMenu;
use App\Models\NavigationMenuItem;
use App\Models\ScheduledConference;
use App\Models\StaticPage;
use App\Models\Timeline;
use App\Models\Topic;
use App\Models\Track;
use App\Models\User;
use App\Panel\Administration\Resources\StaticPageResource\Pages\ListStaticPages;
use App\Panel\Conference\Livewire\NavigationMenuSetting;
use App\Panel\ScheduledConference\Livewire\AuthorGuidance;
use App\Panel\ScheduledConference\Livewire\EditorGuidance;
use App\Panel\ScheduledConference\Livewire\ReviewGuidance;
use App\Panel\ScheduledConference\Livewire\TopicTable;
use App\Panel\ScheduledConference\Livewire\TrackTable;
use App\Panel\ScheduledConference\Resources\AnnouncementResource\Pages\ListAnnouncements;
use App\Panel\ScheduledConference\Resources\TimelineResource\Pages\ManageTimeline;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use RahmanRamsi\LaravelTranslatable\Translate;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class ContentFormsTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);

        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));

        // These tests cover the forms, not the permissions of the editor.
        Gate::before(fn () => true);

        $this->actingAs(User::query()->create([
            'given_name' => 'Content',
            'family_name' => 'Manager',
            'email' => 'content-manager@example.test',
            'password' => 'password123456',
        ]));
    }

    public function test_static_page_form_creates_and_updates_every_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(ListStaticPages::class)
            ->mountAction('create')
            ->assertSeeHtml('mountedActionsData.0.title.en')
            ->assertSeeHtml('mountedActionsData.0.title.id')
            ->assertSeeHtml('mountedActionsData.0.meta.content.id')
            ->setActionData([
                'title' => ['en' => 'Visitor guide', 'id' => 'Panduan pengunjung'],
                'slug' => 'visitor-guide',
                'meta' => ['content' => ['en' => '<p>How to get here</p>', 'id' => '<p>Cara ke lokasi</p>']],
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $page = StaticPage::query()->where('slug', 'visitor-guide')->firstOrFail();

        $this->assertSame('Visitor guide', $page->title);
        $this->assertSame('<p>How to get here</p>', $page->getMeta('content'));
        $this->assertSame('Panduan pengunjung', $page->translated('title', 'id'));
        $this->assertSame('<p>Cara ke lokasi</p>', $page->translated('content', 'id'));

        Livewire::test(ListStaticPages::class)
            ->mountTableAction('edit', $page)
            ->assertTableActionDataSet([
                'title' => ['en' => 'Visitor guide', 'id' => 'Panduan pengunjung'],
            ])
            ->setTableActionData([
                'title' => ['en' => 'Visitor information', 'id' => 'Informasi pengunjung'],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $page = StaticPage::query()->find($page->getKey());

        $this->assertSame('Visitor information', $page->title);
        $this->assertSame('Informasi pengunjung', $page->translated('title', 'id'));
    }

    public function test_static_page_form_requires_only_the_default_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(ListStaticPages::class)
            ->callAction('create', [
                'title' => ['en' => '', 'id' => 'Panduan'],
                'slug' => 'guide',
            ])
            ->assertHasActionErrors(['title.en' => 'required'])
            ->assertHasNoActionErrors(['title.id']);

        Livewire::test(ListStaticPages::class)
            ->callAction('create', [
                'title' => ['en' => 'Guide', 'id' => ''],
                'slug' => 'guide',
            ])
            ->assertHasNoActionErrors();

        $this->assertNull(StaticPage::query()->where('slug', 'guide')->firstOrFail()->getStoredTranslation('title', 'id'));
    }

    public function test_static_page_form_keeps_single_language_behaviour(): void
    {
        Livewire::test(ListStaticPages::class)
            ->mountAction('create')
            ->assertSeeHtml('mountedActionsData.0.title"')
            ->assertDontSeeHtml('mountedActionsData.0.title.en')
            ->setActionData([
                'title' => 'Visitor guide',
                'slug' => 'visitor-guide',
                'meta' => ['content' => '<p>How to get here</p>'],
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $page = StaticPage::query()->where('slug', 'visitor-guide')->firstOrFail();

        $this->assertSame('Visitor guide', $page->title);
        $this->assertSame('<p>How to get here</p>', $page->getMeta('content'));
        $this->assertNoTranslationRows($page);
    }

    public function test_navigation_menu_item_form_saves_every_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        $menu = NavigationMenu::query()->where('handle', 'primary-navigation-menu')->firstOrFail();

        Livewire::test(NavigationMenuSetting::class)
            ->mountAction('addNavigationMenuItemAction', ['navigation_menu_id' => $menu->getKey()])
            ->assertSeeHtml('mountedActionsData.0.label.en')
            ->assertSeeHtml('mountedActionsData.0.label.id')
            ->setActionData([
                'label' => ['en' => '', 'id' => 'Beranda'],
                'type' => 'home',
            ])
            ->callMountedAction()
            ->assertHasActionErrors(['label.en' => 'required'])
            ->assertHasNoActionErrors(['label.id'])
            ->setActionData([
                'label' => ['en' => 'Home page', 'id' => 'Beranda'],
                'type' => 'home',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $item = NavigationMenuItem::query()->where('label', 'Home page')->firstOrFail();

        $this->assertSame('Beranda', $item->translated('label', 'id'));

        Livewire::test(NavigationMenuSetting::class)
            ->mountAction('editNavigationMenuItemAction', ['id' => $item->getKey()])
            ->assertActionDataSet(['label' => ['en' => 'Home page', 'id' => 'Beranda']])
            ->setActionData(['label' => ['en' => 'Home', 'id' => 'Halaman utama']])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $item = NavigationMenuItem::query()->find($item->getKey());

        $this->assertSame('Home', $item->label);
        $this->assertSame('Halaman utama', $item->translated('label', 'id'));
    }

    public function test_navigation_menu_item_form_keeps_single_language_behaviour(): void
    {
        $menu = NavigationMenu::query()->where('handle', 'primary-navigation-menu')->firstOrFail();

        Livewire::test(NavigationMenuSetting::class)
            ->callAction('addNavigationMenuItemAction', ['label' => 'Home page', 'type' => 'home'], ['navigation_menu_id' => $menu->getKey()])
            ->assertHasNoActionErrors();

        $item = NavigationMenuItem::query()->where('label', 'Home page')->firstOrFail();

        $this->assertNoTranslationRows($item);
    }

    public function test_announcement_form_creates_and_updates_every_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(ListAnnouncements::class)
            ->mountAction('create')
            ->assertSeeHtml('mountedActionsData.0.title.id')
            ->assertSeeHtml('mountedActionsData.0.meta.summary.id')
            ->assertSeeHtml('mountedActionsData.0.meta.content.id')
            ->setActionData([
                'title' => ['en' => '', 'id' => 'Pengumuman'],
            ])
            ->callMountedAction()
            ->assertHasActionErrors(['title.en' => 'required'])
            ->assertHasNoActionErrors(['title.id'])
            ->setActionData([
                'title' => ['en' => 'Call for papers', 'id' => 'Undangan makalah'],
                'meta' => [
                    'summary' => ['en' => 'Submit your paper', 'id' => 'Kirim makalah Anda'],
                    'content' => ['en' => '<p>Details</p>', 'id' => '<p>Rincian</p>'],
                ],
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $announcement = Announcement::query()->where('title', 'Call for papers')->firstOrFail();

        $this->assertSame('Submit your paper', $announcement->getMeta('summary'));
        $this->assertSame('Undangan makalah', $announcement->translated('title', 'id'));
        $this->assertSame('Kirim makalah Anda', $announcement->translated('summary', 'id'));
        $this->assertSame('<p>Rincian</p>', $announcement->translated('content', 'id'));

        Livewire::test(ListAnnouncements::class)
            ->mountTableAction('edit', $announcement)
            ->assertTableActionDataSet(['meta.summary' => ['en' => 'Submit your paper', 'id' => 'Kirim makalah Anda']])
            ->setTableActionData(['meta.summary.id' => 'Kirimkan makalah'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame('Kirimkan makalah', Announcement::query()->find($announcement->getKey())->translated('summary', 'id'));
    }

    public function test_announcement_form_keeps_single_language_behaviour(): void
    {
        Livewire::test(ListAnnouncements::class)
            ->callAction('create', [
                'title' => 'Call for papers',
                'meta' => ['summary' => 'Submit your paper'],
            ])
            ->assertHasNoActionErrors();

        $announcement = Announcement::query()->where('title', 'Call for papers')->firstOrFail();

        $this->assertSame('Submit your paper', $announcement->getMeta('summary'));
        $this->assertNoTranslationRows($announcement);
    }

    public function test_timeline_form_creates_and_updates_every_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(ManageTimeline::class)
            ->mountAction('create')
            ->assertSeeHtml('mountedActionsData.0.name.id')
            ->assertSeeHtml('mountedActionsData.0.description.id')
            ->setActionData([
                'name' => ['en' => '', 'id' => 'Batas akhir'],
                'date' => now()->addWeek()->toDateString(),
            ])
            ->callMountedAction()
            ->assertHasActionErrors(['name.en' => 'required'])
            ->assertHasNoActionErrors(['name.id'])
            ->setActionData([
                'name' => ['en' => 'Deadline', 'id' => 'Batas akhir'],
                'description' => ['en' => 'Last day to submit', 'id' => 'Hari terakhir pengiriman'],
                'date' => now()->addWeek()->toDateString(),
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $timeline = Timeline::query()->where('name', 'Deadline')->firstOrFail();

        $this->assertSame('Last day to submit', $timeline->description);
        $this->assertSame('Batas akhir', $timeline->translated('name', 'id'));
        $this->assertSame('Hari terakhir pengiriman', $timeline->translated('description', 'id'));

        Livewire::test(ManageTimeline::class)
            ->mountTableAction('edit', $timeline)
            ->assertTableActionDataSet(['name' => ['en' => 'Deadline', 'id' => 'Batas akhir']])
            ->setTableActionData(['name' => ['en' => 'Final deadline', 'id' => 'Batas akhir final']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $timeline = Timeline::query()->find($timeline->getKey());

        $this->assertSame('Final deadline', $timeline->name);
        $this->assertSame('Batas akhir final', $timeline->translated('name', 'id'));
    }

    public function test_timeline_form_keeps_single_language_behaviour(): void
    {
        Livewire::test(ManageTimeline::class)
            ->callAction('create', [
                'name' => 'Deadline',
                'description' => 'Last day to submit',
                'date' => now()->addWeek()->toDateString(),
            ])
            ->assertHasNoActionErrors();

        $timeline = Timeline::query()->where('name', 'Deadline')->firstOrFail();

        $this->assertSame('Last day to submit', $timeline->description);
        $this->assertNoTranslationRows($timeline);
    }

    public function test_track_form_creates_and_updates_every_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(TrackTable::class)
            ->loadTable()
            ->mountTableAction('create')
            ->assertSeeHtml('mountedTableActionsData.0.title.id')
            ->assertSeeHtml('mountedTableActionsData.0.meta.policy.id')
            ->setTableActionData([
                'title' => ['en' => '', 'id' => 'Kimia'],
                'abbreviation' => 'CHEM',
            ])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['title.en' => 'required'])
            ->assertHasNoTableActionErrors(['title.id'])
            ->setTableActionData([
                'title' => ['en' => 'Chemistry', 'id' => 'Kimia'],
                'abbreviation' => 'CHEM',
                'meta' => ['policy' => ['en' => '<p>Chemistry papers</p>', 'id' => '<p>Makalah kimia</p>']],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $track = Track::query()->where('title', 'Chemistry')->firstOrFail();

        $this->assertSame('<p>Chemistry papers</p>', $track->getMeta('policy'));
        $this->assertSame('Kimia', $track->translated('title', 'id'));
        $this->assertSame('<p>Makalah kimia</p>', $track->translated('policy', 'id'));

        Livewire::test(TrackTable::class)
            ->loadTable()
            ->mountTableAction('edit', $track)
            ->assertTableActionDataSet(['title' => ['en' => 'Chemistry', 'id' => 'Kimia']])
            ->setTableActionData(['title' => ['en' => 'Applied Chemistry', 'id' => 'Kimia Terapan']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $track = Track::query()->find($track->getKey());

        $this->assertSame('Applied Chemistry', $track->title);
        $this->assertSame('Kimia Terapan', $track->translated('title', 'id'));
    }

    public function test_track_form_keeps_single_language_behaviour(): void
    {
        Livewire::test(TrackTable::class)
            ->loadTable()
            ->callTableAction('create', data: [
                'title' => 'Chemistry',
                'abbreviation' => 'CHEM',
                'meta' => ['policy' => '<p>Chemistry papers</p>'],
            ])
            ->assertHasNoTableActionErrors();

        $track = Track::query()->where('title', 'Chemistry')->firstOrFail();

        $this->assertSame('<p>Chemistry papers</p>', $track->getMeta('policy'));
        $this->assertNoTranslationRows($track);
    }

    public function test_topic_form_creates_and_updates_every_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(TopicTable::class)
            ->mountTableAction('createtopic')
            ->assertSeeHtml('mountedTableActionsData.0.name.en')
            ->assertSeeHtml('mountedTableActionsData.0.name.id')
            ->setTableActionData(['name' => ['en' => '', 'id' => 'Energi']])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['name.en' => 'required'])
            ->assertHasNoTableActionErrors(['name.id'])
            ->setTableActionData(['name' => ['en' => 'Energy', 'id' => 'Energi']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $topic = Topic::query()->where('name', 'Energy')->firstOrFail();

        $this->assertSame('Energi', $topic->translated('name', 'id'));

        Livewire::test(TopicTable::class)
            ->mountTableAction('edit', $topic)
            ->assertTableActionDataSet(['name' => ['en' => 'Energy', 'id' => 'Energi']])
            ->setTableActionData(['name' => ['en' => 'Renewable energy', 'id' => 'Energi terbarukan']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $topic = Topic::query()->find($topic->getKey());

        $this->assertSame('Renewable energy', $topic->name);
        $this->assertSame('Energi terbarukan', $topic->translated('name', 'id'));
    }

    public function test_topic_form_keeps_single_language_behaviour(): void
    {
        Livewire::test(TopicTable::class)
            ->callTableAction('createtopic', data: ['name' => 'Energy'])
            ->assertHasNoTableActionErrors();

        $this->assertNoTranslationRows(Topic::query()->where('name', 'Energy')->firstOrFail());
    }

    public function test_author_guidance_renders_and_saves_every_language(): void
    {
        $this->enableLanguages(['en', 'id']);
        $this->scheduledConference->setMeta('author_guidelines', '<p>Use the template</p>');

        Livewire::test(AuthorGuidance::class)
            ->assertSet('formData.meta.author_guidelines.en', '<p>Use the template</p>')
            ->assertSet('formData.meta.author_guidelines.id', null)
            ->assertSeeHtml('formData.meta.author_guidelines.en')
            ->assertSeeHtml('formData.meta.author_guidelines.id')
            ->assertSeeHtml('formData.meta.before_you_begin.en')
            ->assertSeeHtml('formData.meta.before_you_begin.id')
            ->assertSeeHtml('formData.meta.submission_checklist.en')
            ->assertSeeHtml('formData.meta.submission_checklist.id')
            ->set('formData.meta.author_guidelines.id', '<p>Gunakan templat</p>')
            ->set('formData.meta.before_you_begin.en', '<p>Before you begin</p>')
            ->set('formData.meta.before_you_begin.id', '<p>Sebelum mulai</p>')
            ->set('formData.meta.submission_checklist.en', '<p>Checklist</p>')
            ->set('formData.meta.submission_checklist.id', '<p>Daftar periksa</p>')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasNoErrors();

        $this->assertGuidanceSaved([
            'author_guidelines' => ['en' => '<p>Use the template</p>', 'id' => '<p>Gunakan templat</p>'],
            'before_you_begin' => ['en' => '<p>Before you begin</p>', 'id' => '<p>Sebelum mulai</p>'],
            'submission_checklist' => ['en' => '<p>Checklist</p>', 'id' => '<p>Daftar periksa</p>'],
        ]);

        Livewire::test(AuthorGuidance::class)
            ->assertSet('formData.meta.author_guidelines', ['en' => '<p>Use the template</p>', 'id' => '<p>Gunakan templat</p>'])
            ->assertSet('formData.meta.before_you_begin', ['en' => '<p>Before you begin</p>', 'id' => '<p>Sebelum mulai</p>'])
            ->assertSet('formData.meta.submission_checklist', ['en' => '<p>Checklist</p>', 'id' => '<p>Daftar periksa</p>']);
    }

    public function test_review_guidance_saves_every_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(ReviewGuidance::class)
            ->assertSeeHtml('formData.meta.review_guidelines.en')
            ->assertSeeHtml('formData.meta.review_guidelines.id')
            ->assertSeeHtml('formData.meta.competing_interests.en')
            ->assertSeeHtml('formData.meta.competing_interests.id')
            ->set('formData.meta.review_guidelines.en', '<p>Be constructive</p>')
            ->set('formData.meta.review_guidelines.id', '<p>Bersikap membangun</p>')
            ->set('formData.meta.competing_interests.en', '<p>Competing interests</p>')
            ->set('formData.meta.competing_interests.id', '<p>Konflik kepentingan</p>')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasNoErrors();

        $this->assertGuidanceSaved([
            'review_guidelines' => ['en' => '<p>Be constructive</p>', 'id' => '<p>Bersikap membangun</p>'],
            'competing_interests' => ['en' => '<p>Competing interests</p>', 'id' => '<p>Konflik kepentingan</p>'],
        ]);

        Livewire::test(ReviewGuidance::class)
            ->assertSet('formData.meta.review_guidelines', ['en' => '<p>Be constructive</p>', 'id' => '<p>Bersikap membangun</p>'])
            ->assertSet('formData.meta.competing_interests', ['en' => '<p>Competing interests</p>', 'id' => '<p>Konflik kepentingan</p>']);
    }

    public function test_editor_guidance_saves_every_language(): void
    {
        $this->enableLanguages(['en', 'id']);

        Livewire::test(EditorGuidance::class)
            ->assertSeeHtml('formData.meta.editor_guidelines.en')
            ->assertSeeHtml('formData.meta.editor_guidelines.id')
            ->set('formData.meta.editor_guidelines.en', '<p>Check scope</p>')
            ->set('formData.meta.editor_guidelines.id', '<p>Periksa cakupan</p>')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasNoErrors();

        $this->assertGuidanceSaved([
            'editor_guidelines' => ['en' => '<p>Check scope</p>', 'id' => '<p>Periksa cakupan</p>'],
        ]);

        Livewire::test(EditorGuidance::class)
            ->assertSet('formData.meta.editor_guidelines', ['en' => '<p>Check scope</p>', 'id' => '<p>Periksa cakupan</p>']);
    }

    public function test_guidance_forms_keep_single_language_behaviour(): void
    {
        Livewire::test(AuthorGuidance::class)
            ->assertSeeHtml('formData.meta.author_guidelines"')
            ->assertDontSeeHtml('formData.meta.author_guidelines.en')
            ->assertDontSeeHtml('formData.meta.before_you_begin.en')
            ->assertDontSeeHtml('formData.meta.submission_checklist.en')
            ->set('formData.meta.author_guidelines', '<p>Use the template</p>')
            ->set('formData.meta.before_you_begin', '<p>Before you begin</p>')
            ->set('formData.meta.submission_checklist', '<p>Checklist</p>')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasNoErrors();

        Livewire::test(ReviewGuidance::class)
            ->assertDontSeeHtml('formData.meta.review_guidelines.en')
            ->assertDontSeeHtml('formData.meta.competing_interests.en')
            ->set('formData.meta.review_guidelines', '<p>Be constructive</p>')
            ->set('formData.meta.competing_interests', '<p>Competing interests</p>')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasNoErrors();

        Livewire::test(EditorGuidance::class)
            ->assertSeeHtml('formData.meta.editor_guidelines"')
            ->assertDontSeeHtml('formData.meta.editor_guidelines.en')
            ->set('formData.meta.editor_guidelines', '<p>Check scope</p>')
            ->call('mountFormComponentAction', 'formData.saveAction', 'save')
            ->assertHasNoErrors();

        $scheduledConference = ScheduledConference::query()->find($this->scheduledConference->getKey());

        foreach ([
            'author_guidelines' => '<p>Use the template</p>',
            'before_you_begin' => '<p>Before you begin</p>',
            'submission_checklist' => '<p>Checklist</p>',
            'review_guidelines' => '<p>Be constructive</p>',
            'competing_interests' => '<p>Competing interests</p>',
            'editor_guidelines' => '<p>Check scope</p>',
        ] as $key => $value) {
            $this->assertSame($value, $scheduledConference->getMeta($key), $key);
        }

        $this->assertFalse(Translate::query()
            ->where('translatable_type', $scheduledConference->getMorphClass())
            ->where('translatable_id', $scheduledConference->getKey())
            ->whereIn('key', [
                'author_guidelines',
                'before_you_begin',
                'submission_checklist',
                'review_guidelines',
                'competing_interests',
                'editor_guidelines',
            ])
            ->exists());
    }

    /**
     * @param  array<string, array{en: string, id: string}>  $expected
     */
    protected function assertGuidanceSaved(array $expected): void
    {
        $scheduledConference = ScheduledConference::query()->find($this->scheduledConference->getKey());

        foreach ($expected as $key => $values) {
            $this->assertSame($values['en'], $scheduledConference->getMeta($key), "{$key} default-language meta");
            $this->assertSame($values['en'], $scheduledConference->getStoredTranslation($key, 'en'), "{$key} en row");
            $this->assertSame($values['id'], $scheduledConference->getStoredTranslation($key, 'id'), "{$key} id row");
        }
    }

    protected function assertNoTranslationRows(Model $model): void
    {
        $this->assertFalse(Translate::query()
            ->where('translatable_type', $model->getMorphClass())
            ->where('translatable_id', $model->getKey())
            ->exists());
    }
}
