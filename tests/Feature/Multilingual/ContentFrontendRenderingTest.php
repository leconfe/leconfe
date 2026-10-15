<?php

namespace Tests\Feature\Multilingual;

use App\Frontend\ScheduledConference\Pages\AnnouncementPage;
use App\Frontend\ScheduledConference\Pages\Announcements;
use App\Frontend\ScheduledConference\Pages\Timelines;
use App\Models\Announcement;
use App\Models\Conference;
use App\Models\NavigationMenu;
use App\Models\NavigationMenuItem;
use App\Models\ScheduledConference;
use App\Models\StaticPage;
use App\Models\Timeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class ContentFrontendRenderingTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    /**
     * @var array<string, string>
     */
    protected array $parameters;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);
        $this->parameters = ['conference' => $this->conference->path, 'serie' => $this->scheduledConference->path];

        $this->enableLanguages(['en', 'id', 'fr']);
    }

    public function test_static_page_renders_the_session_language_with_fallback(): void
    {
        $page = StaticPage::query()->create(['title' => 'Visitor guide', 'slug' => 'visitor-guide']);
        $page->setMeta('content', '<p>How to get here</p>');
        $page->setTranslation('title', 'id', 'Panduan pengunjung');
        $page->setTranslation('content', 'id', '<p>Cara ke lokasi</p>');

        $legacy = StaticPage::query()->create(['title' => 'Legacy page', 'slug' => 'legacy']);
        $legacy->setMeta('content', '<p>Legacy content</p>');

        $url = fn (string $slug) => route('livewirePageGroup.scheduledConference.pages.static-page', [...$this->parameters, 'staticPage' => $slug]);

        $this->frontend('id', $url('visitor-guide'))
            ->assertSee('Panduan pengunjung')
            ->assertSee('Cara ke lokasi')
            ->assertDontSee('How to get here');

        $this->frontend('fr', $url('visitor-guide'))
            ->assertSee('Visitor guide')
            ->assertSee('How to get here')
            ->assertDontSee('Panduan pengunjung');

        $this->frontend('id', $url('legacy'))
            ->assertSee('Legacy page')
            ->assertSee('Legacy content');
    }

    public function test_navigation_labels_render_the_session_language_in_desktop_and_mobile_menus(): void
    {
        $menu = NavigationMenu::query()->where('handle', 'primary-navigation-menu')->firstOrFail();

        $translated = $this->makeMenuItem($menu, 'Visitor information', 'https://example.test/visitors', 90);
        $translated->setTranslation('label', 'id', 'Informasi pengunjung');
        $this->makeMenuItem($menu, 'Legacy link', 'https://example.test/legacy', 91);

        $this->frontend('id', route('livewirePageGroup.scheduledConference.pages.home', $this->parameters))
            ->assertSee('Informasi pengunjung')
            ->assertSee('Legacy link')
            ->assertDontSee('Visitor information');

        $this->frontend('fr', route('livewirePageGroup.scheduledConference.pages.home', $this->parameters))
            ->assertSee('Visitor information')
            ->assertSee('Legacy link')
            ->assertDontSee('Informasi pengunjung');

        app()->setLocale('id');
        $mobile = $this->renderMobileMenu();
        $this->assertStringContainsString('Informasi pengunjung', $mobile);
        $this->assertStringContainsString('Legacy link', $mobile);
        $this->assertStringNotContainsString('Visitor information', $mobile);

        app()->setLocale('fr');
        $mobile = $this->renderMobileMenu();
        $this->assertStringContainsString('Visitor information', $mobile);
        $this->assertStringNotContainsString('Informasi pengunjung', $mobile);
    }

    public function test_navigation_label_keeps_the_username_placeholder(): void
    {
        $menu = NavigationMenu::query()->where('handle', 'user-navigation-menu')->firstOrFail();
        $item = $this->makeMenuItem($menu, 'Hello {$username}', 'https://example.test/profile', 90);
        $item->setTranslation('label', 'id', 'Halo {$username}');

        $this->actingAs(\App\Models\User::query()->create([
            'given_name' => 'Ana',
            'family_name' => 'Lee',
            'email' => 'ana@example.test',
            'password' => 'password123456',
        ]));

        app()->setLocale('id');

        $this->assertSame('Halo Ana Lee', $item->getLabel());
        $this->assertSame('Halo {$username}', $item->translated('label'));
    }

    public function test_announcements_render_the_session_language_with_fallback(): void
    {
        $announcement = Announcement::query()->create(['title' => 'Call for papers']);
        $announcement->setManyMeta(['summary' => 'Submit your paper', 'content' => '<p>Full details</p>']);
        $announcement->setTranslation('title', 'id', 'Undangan makalah');
        $announcement->setTranslation('summary', 'id', 'Kirim makalah Anda');
        $announcement->setTranslation('content', 'id', '<p>Rincian lengkap</p>');

        $legacy = Announcement::query()->create(['title' => 'Venue update']);
        $legacy->setManyMeta(['summary' => 'The venue has moved', 'content' => '<p>New venue</p>']);

        $list = route(Announcements::getRouteName('scheduledConference'), $this->parameters);
        $detail = fn (Announcement $item) => route(AnnouncementPage::getRouteName('scheduledConference'), [...$this->parameters, 'announcement' => $item->getKey()]);

        $this->frontend('id', $list)
            ->assertSee('Undangan makalah')
            ->assertSee('Kirim makalah Anda')
            ->assertSee('Venue update')
            ->assertSee('The venue has moved')
            ->assertDontSee('Call for papers');

        $this->frontend('fr', $list)
            ->assertSee('Call for papers')
            ->assertSee('Submit your paper')
            ->assertDontSee('Undangan makalah');

        $this->frontend('id', $detail($announcement))
            ->assertSee('Undangan makalah')
            ->assertSee('Rincian lengkap')
            ->assertDontSee('Full details');

        $this->frontend('fr', $detail($announcement))
            ->assertSee('Call for papers')
            ->assertSee('Full details');

        $this->frontend('id', $detail($legacy))
            ->assertSee('Venue update')
            ->assertSee('New venue');
    }

    public function test_expired_announcements_stay_hidden(): void
    {
        Announcement::query()->create(['title' => 'Old news', 'expires_at' => now()->subWeek()]);
        Announcement::query()->create(['title' => 'Current news']);

        $this->frontend('en', route(Announcements::getRouteName('scheduledConference'), $this->parameters))
            ->assertSee('Current news')
            ->assertDontSee('Old news');
    }

    public function test_timelines_render_the_session_language_with_fallback(): void
    {
        $timeline = Timeline::query()->create([
            'name' => 'Submission deadline',
            'description' => 'Last day to submit',
            'date' => now()->addWeek(),
        ]);
        $timeline->setTranslation('name', 'id', 'Batas pengiriman');
        $timeline->setTranslation('description', 'id', 'Hari terakhir pengiriman');

        Timeline::query()->create([
            'name' => 'Opening ceremony',
            'description' => 'Welcome speech',
            'date' => now()->addWeeks(2),
        ]);

        $url = route(Timelines::getRouteName('scheduledConference'), $this->parameters);

        $this->frontend('id', $url)
            ->assertSee('Batas pengiriman')
            ->assertSee('Hari terakhir pengiriman')
            ->assertSee('Opening ceremony')
            ->assertSee('Welcome speech')
            ->assertDontSee('Submission deadline');

        $this->frontend('fr', $url)
            ->assertSee('Submission deadline')
            ->assertSee('Last day to submit')
            ->assertDontSee('Batas pengiriman');
    }

    protected function frontend(string $locale, string $url)
    {
        return $this->withoutVite()
            ->withSession(['locale' => $locale])
            ->get($url)
            ->assertOk();
    }

    protected function makeMenuItem(NavigationMenu $menu, string $label, string $url, int $order): NavigationMenuItem
    {
        $item = NavigationMenuItem::query()->forceCreate([
            'navigation_menu_id' => $menu->getKey(),
            'label' => $label,
            'type' => 'remote-url',
            'order_column' => $order,
        ]);
        $item->setMeta('url', $url);

        return $item;
    }

    protected function renderMobileMenu(): string
    {
        return Blade::render('<x-website::navigation-menu-mobile :headerLogo="$headerLogo" />', ['headerLogo' => null]);
    }
}
