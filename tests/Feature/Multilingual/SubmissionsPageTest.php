<?php

namespace Tests\Feature\Multilingual;

use App\Frontend\ScheduledConference\Pages\Submissions;
use App\Models\Conference;
use App\Models\NavigationItemType\Submissions as SubmissionsNavigationItem;
use App\Models\NavigationMenu;
use App\Models\NavigationMenuItem;
use App\Models\ScheduledConference;
use App\Models\Timeline;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages\CreateSubmission;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\StoresDefaultLanguageRows;
use Tests\TestCase;

class SubmissionsPageTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;
    use StoresDefaultLanguageRows;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected Track $track;

    protected string $url;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);

        $this->track = Track::query()->create(['title' => 'Chemistry', 'abbreviation' => 'CHEM']);

        $this->url = route(Submissions::getRouteName('scheduledConference'), [
            'conference' => $this->conference->path,
            'serie' => $this->scheduledConference->path,
        ]);

        $this->enableLanguages(['en', 'id', 'fr']);
    }

    public function test_page_renders_translated_guidance_for_the_session_language_with_fallback(): void
    {
        $this->scheduledConference->setManyMeta([
            'author_guidelines' => '<p>Use the paper template</p>',
            'submission_checklist' => '<p>The paper is original</p>',
            'privacy_statement' => '<p>We keep your data safe</p>',
        ]);
        $this->scheduledConference->setTranslation('author_guidelines', 'id', '<p>Gunakan templat makalah</p>');
        $this->scheduledConference->setTranslation('submission_checklist', 'id', '<p>Makalah asli</p>');
        $this->scheduledConference->setTranslation('privacy_statement', 'id', '<p>Data Anda aman</p>');

        $this->track->setMeta('policy', '<p>Chemistry papers only</p>');
        $this->track->setTranslation('title', 'id', 'Kimia');
        $this->track->setTranslation('policy', 'id', '<p>Hanya makalah kimia</p>');

        $legacyTrack = Track::query()->create(['title' => 'Physics', 'abbreviation' => 'PHY']);
        $legacyTrack->setMeta('policy', '<p>Physics papers only</p>');

        $this->visit('id')
            ->assertSeeInOrder([
                __('general.author_guidelines', [], 'id'),
                'Gunakan templat makalah',
                __('general.submission_checklist', [], 'id'),
                'Makalah asli',
                __('general.track_policies', [], 'id'),
                'Kimia',
                'Hanya makalah kimia',
                'Physics',
                'Physics papers only',
                __('general.privacy_statement', [], 'id'),
                'Data Anda aman',
            ], false)
            ->assertDontSee('Use the paper template')
            ->assertDontSee('Chemistry papers only');

        $this->visit('fr')
            ->assertSee('Use the paper template', false)
            ->assertSee('The paper is original', false)
            ->assertSee('Chemistry')
            ->assertSee('Chemistry papers only', false)
            ->assertSee('We keep your data safe', false)
            ->assertDontSee('Gunakan templat makalah')
            ->assertDontSee('Kimia');
    }

    public function test_page_prefers_the_default_language_row_over_the_legacy_value(): void
    {
        $this->scheduledConference->setManyMeta([
            'author_guidelines' => '<p>Stored guidelines</p>',
            'submission_checklist' => '<p>Stored checklist</p>',
            'privacy_statement' => '<p>Stored privacy</p>',
        ]);
        $this->storeDefaultLanguageRow($this->scheduledConference, 'author_guidelines', '<p>Follow the house style</p>');
        $this->storeDefaultLanguageRow($this->scheduledConference, 'submission_checklist', '<p>References are complete</p>');
        $this->storeDefaultLanguageRow($this->scheduledConference, 'privacy_statement', '<p>Data stays private</p>');
        $this->scheduledConference->setTranslation('submission_checklist', 'id', '<p>Referensi lengkap</p>');

        $this->track->update(['title' => 'Stored track title']);
        $this->track->setMeta('policy', '<p>Stored track policy</p>');
        $this->storeDefaultLanguageRow($this->track, 'title', 'Materials science');
        $this->storeDefaultLanguageRow($this->track, 'policy', '<p>Materials papers only</p>');

        $this->visit('id')
            ->assertSee('Follow the house style', false)
            ->assertSee('Referensi lengkap', false)
            ->assertSee('Materials science')
            ->assertSee('Materials papers only', false)
            ->assertSee('Data stays private', false)
            ->assertDontSee('Stored guidelines')
            ->assertDontSee('References are complete')
            ->assertDontSee('Stored checklist')
            ->assertDontSee('Stored track title')
            ->assertDontSee('Stored track policy')
            ->assertDontSee('Stored privacy');
    }

    public function test_page_uses_legacy_values_without_translations(): void
    {
        $this->scheduledConference->setManyMeta([
            'author_guidelines' => '<p>Legacy guidelines</p>',
            'submission_checklist' => '<p>Legacy checklist</p>',
            'privacy_statement' => '<p>Legacy privacy</p>',
        ]);
        $this->track->setMeta('policy', '<p>Legacy track policy</p>');

        $this->visit('id')
            ->assertSee('Legacy guidelines', false)
            ->assertSee('Legacy checklist', false)
            ->assertSee('Chemistry')
            ->assertSee('Legacy track policy', false)
            ->assertSee('Legacy privacy', false);
    }

    public function test_empty_sections_are_hidden(): void
    {
        $this->scheduledConference->setManyMeta([
            'author_guidelines' => '<p></p>',
            'submission_checklist' => '',
            'privacy_statement' => null,
        ]);
        $this->scheduledConference->setTranslation('author_guidelines', 'id', '<p>Hanya bahasa Indonesia</p>');

        $this->visit('en')
            ->assertDontSee('class="author-guidelines', false)
            ->assertDontSee('class="submission-checklist', false)
            ->assertDontSee('class="track-policies', false)
            ->assertDontSee('class="privacy-statement', false)
            ->assertDontSee(__('general.track_policies'));

        $this->visit('id')
            ->assertSee('class="author-guidelines', false)
            ->assertSee('Hanya bahasa Indonesia', false)
            ->assertDontSee('class="submission-checklist', false)
            ->assertDontSee('class="privacy-statement', false);
    }

    public function test_guests_are_asked_to_log_in_or_register(): void
    {
        $this->openSubmissions();

        $this->visit('en')
            ->assertSee(__('general.submissions_guest_notice'))
            ->assertSee('href="'.route('livewirePageGroup.scheduledConference.pages.login').'"', false)
            ->assertSee('href="'.route('livewirePageGroup.scheduledConference.pages.register').'"', false)
            ->assertDontSee(__('general.make_new_submission'))
            ->assertDontSee(__('general.not_accepting_submissions'));
    }

    public function test_logged_in_users_get_submission_links(): void
    {
        $this->openSubmissions();
        $this->actingAs($this->makeUser());

        $this->visit('en')
            ->assertSee(__('general.make_new_submission'))
            ->assertSee('href="'.route('filament.scheduledConference.resources.submissions.create').'"', false)
            ->assertSee(__('general.view_your_submissions'))
            ->assertSee('href="'.route('filament.scheduledConference.resources.submissions.index').'"', false)
            ->assertDontSee(__('general.submissions_guest_notice'));
    }

    public function test_closed_submissions_show_the_not_accepting_notice(): void
    {
        $this->visit('en')
            ->assertSee(__('general.not_accepting_submissions'))
            ->assertDontSee(__('general.submissions_guest_notice'));

        $this->actingAs($this->makeUser());

        $this->visit('en')
            ->assertSee(__('general.not_accepting_submissions'))
            ->assertDontSee(__('general.make_new_submission'))
            ->assertSee(__('general.view_your_submissions'));
    }

    public function test_submissions_navigation_item_links_to_the_page(): void
    {
        $menu = NavigationMenu::query()->where('handle', 'primary-navigation-menu')->firstOrFail();
        $item = NavigationMenuItem::query()->forceCreate([
            'navigation_menu_id' => $menu->getKey(),
            'label' => 'Author information',
            'type' => 'submissions',
            'order_column' => 90,
        ]);

        $this->assertArrayHasKey('submissions', NavigationMenuItem::getTypeOptions());
        $this->assertTrue(SubmissionsNavigationItem::getIsDisplayed($item));
        $this->assertSame($this->url, $item->getUrl());

        $this->visit('en', route('livewirePageGroup.scheduledConference.pages.home', [
            'conference' => $this->conference->path,
            'serie' => $this->scheduledConference->path,
        ]))
            ->assertSee('href="'.$this->url.'"', false)
            ->assertSee('Author information');
    }

    public function test_submissions_navigation_item_is_not_added_to_menus_automatically(): void
    {
        $this->assertFalse(NavigationMenuItem::query()->where('type', 'submissions')->exists());
    }

    public function test_wizard_shows_translated_guidance_and_the_guidelines_link(): void
    {
        $this->openSubmissions();
        $this->scheduledConference->setManyMeta([
            'before_you_begin' => '<p>Read this first</p>',
            'submission_checklist' => '<p>The paper is original</p>',
            'author_guidelines' => '<p>Use the paper template</p>',
        ]);
        $this->scheduledConference->setTranslation('before_you_begin', 'id', '<p>Baca ini dulu</p>');
        $this->scheduledConference->setTranslation('submission_checklist', 'id', '<p>Makalah asli</p>');

        $this->actingAsAuthorInPanel();

        app()->setLocale('id');

        Livewire::test(CreateSubmission::class)
            ->assertSee('Baca ini dulu')
            ->assertSee('Makalah asli')
            ->assertDontSee('Read this first')
            ->assertSee(__('general.read_author_guidelines'))
            ->assertSeeHtml('href="'.e($this->url).'" target="_blank" rel="noopener"');

        app()->setLocale('fr');

        Livewire::test(CreateSubmission::class)
            ->assertSee('Read this first')
            ->assertSee('The paper is original')
            ->assertDontSee('Baca ini dulu');
    }

    public function test_wizard_guidance_prefers_the_default_language_row_over_the_legacy_value(): void
    {
        $this->openSubmissions();
        $this->scheduledConference->setManyMeta([
            'before_you_begin' => '<p>Stored intro</p>',
            'submission_checklist' => '<p>Stored checklist</p>',
        ]);
        $this->storeDefaultLanguageRow($this->scheduledConference, 'before_you_begin', '<p>Prepare your files</p>');
        $this->storeDefaultLanguageRow($this->scheduledConference, 'submission_checklist', '<p>Figures are attached</p>');

        $this->actingAsAuthorInPanel();

        app()->setLocale('id');

        Livewire::test(CreateSubmission::class)
            ->assertSee('Prepare your files')
            ->assertSee('Figures are attached')
            ->assertDontSee('Stored intro')
            ->assertDontSee('Stored checklist');
    }

    public function test_wizard_hides_the_guidelines_link_without_guidelines(): void
    {
        $this->openSubmissions();
        $this->scheduledConference->setMeta('author_guidelines', '<p></p>');

        $this->actingAsAuthorInPanel();

        Livewire::test(CreateSubmission::class)
            ->assertSee(__('general.before_you_begin'))
            ->assertDontSee(__('general.read_author_guidelines'))
            ->assertDontSeeHtml('href="'.e($this->url).'"');
    }

    protected function openSubmissions(): void
    {
        Timeline::query()->create([
            'name' => 'Submission open',
            'date' => now()->subDay(),
            'type' => Timeline::TYPE_SUBMISSION_OPEN,
        ]);
    }

    protected function actingAsAuthorInPanel(): void
    {
        $this->actingAs($this->makeUser());
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));

        // The wizard is covered here, not the author permissions.
        Gate::before(fn () => true);
    }

    protected function makeUser(): User
    {
        return User::query()->create([
            'given_name' => 'Ana',
            'family_name' => 'Author',
            'email' => 'ana.author@example.test',
            'password' => 'password123456',
        ]);
    }

    protected function visit(string $locale, ?string $url = null)
    {
        return $this->withoutVite()
            ->withSession(['locale' => $locale])
            ->get($url ?? $this->url)
            ->assertOk();
    }
}
