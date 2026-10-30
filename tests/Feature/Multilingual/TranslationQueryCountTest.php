<?php

namespace Tests\Feature\Multilingual;

use App\Frontend\ScheduledConference\Pages\Announcements;
use App\Models\Announcement;
use App\Models\Conference;
use App\Models\NavigationMenu;
use App\Models\NavigationMenuItem;
use App\Models\ScheduledConference;
use App\Models\Track;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class TranslationQueryCountTest extends TestCase
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

        $this->enableLanguages(['en', 'id']);
    }

    public function test_announcement_list_translation_queries_do_not_grow_with_the_number_of_items(): void
    {
        $this->makeAnnouncements(2);
        $few = $this->countTranslateQueries(Announcement::class, fn () => $this->getAnnouncementList()->assertSee('Pengumuman 2'));

        $this->makeAnnouncements(4, offset: 2);
        $many = $this->countTranslateQueries(Announcement::class, fn () => $this->getAnnouncementList()->assertSee('Pengumuman 6'));

        $this->assertGreaterThan(0, $few);
        $this->assertSame($few, $many);
    }

    public function test_navigation_menu_translation_queries_do_not_grow_with_the_number_of_items(): void
    {
        $menu = NavigationMenu::query()->where('handle', 'primary-navigation-menu')->firstOrFail();

        $this->makeMenuItems($menu, 2);
        $few = $this->countTranslateQueries(NavigationMenuItem::class, fn () => $this->renderMenus('Tautan 2'));

        $this->makeMenuItems($menu, 4, offset: 2);
        $many = $this->countTranslateQueries(NavigationMenuItem::class, fn () => $this->renderMenus('Tautan 6'));

        $this->assertGreaterThan(0, $few);
        $this->assertSame($few, $many);
    }

    public function test_translation_edits_on_cached_models_are_visible_on_the_next_read(): void
    {
        $menu = NavigationMenu::query()->where('handle', 'primary-navigation-menu')->firstOrFail();
        $item = $this->makeMenuItems($menu, 1)[0];

        app()->setLocale('id');
        $this->renderMenus('Tautan 1');

        NavigationMenuItem::query()->find($item->getKey())->setTranslation('label', 'id', 'Tautan baru');

        $html = $this->renderMenus('Tautan baru');
        $this->assertStringNotContainsString('Tautan 1<', $html);

        $track = Track::query()->create(['title' => 'Chemistry', 'abbreviation' => 'CHEM']);
        $track->setTranslation('title', 'id', 'Kimia');

        $this->assertSame('Kimia', Track::query()->withTranslations()->find($track->getKey())->translated('title'));

        Track::query()->find($track->getKey())->setTranslation('title', 'id', 'Ilmu Kimia');

        $this->assertSame('Ilmu Kimia', Track::query()->withTranslations()->find($track->getKey())->translated('title'));
        $this->assertSame('Ilmu Kimia', Track::query()->find($track->getKey())->translated('title'));
    }

    protected function getAnnouncementList()
    {
        return $this->withoutVite()
            ->withSession(['locale' => 'id'])
            ->get(route(Announcements::getRouteName('scheduledConference'), [
                'conference' => $this->conference->path,
                'serie' => $this->scheduledConference->path,
            ]))
            ->assertOk();
    }

    protected function renderMenus(string $expected): string
    {
        app()->setLocale('id');

        $html = Blade::render('<x-website::navigation-menu :items="$items" />', ['items' => app()->getNavigationItems('primary-navigation-menu')])
            .Blade::render('<x-website::navigation-menu-mobile :headerLogo="$headerLogo" />', ['headerLogo' => null]);

        $this->assertStringContainsString($expected, $html);

        return $html;
    }

    /**
     * Count the queries that read translations of the given model class.
     */
    protected function countTranslateQueries(string $model, callable $callback): int
    {
        $count = 0;
        $morphClass = (new $model)->getMorphClass();

        DB::listen(function (QueryExecuted $query) use (&$count, $morphClass) {
            if (preg_match('/\bfrom\s+["`]?translate["`]?/i', $query->sql) && in_array($morphClass, $query->bindings, true)) {
                $count++;
            }
        });

        $callback();

        app('events')->forget(QueryExecuted::class);

        return $count;
    }

    protected function makeAnnouncements(int $count, int $offset = 0): void
    {
        foreach (range($offset + 1, $offset + $count) as $number) {
            $announcement = Announcement::query()->create(['title' => "Announcement {$number}"]);
            $announcement->setMeta('summary', "Summary {$number}");
            $announcement->setTranslation('title', 'id', "Pengumuman {$number}");
            $announcement->setTranslation('summary', 'id', "Ringkasan {$number}");
        }
    }

    /**
     * @return array<int, NavigationMenuItem>
     */
    protected function makeMenuItems(NavigationMenu $menu, int $count, int $offset = 0): array
    {
        $items = [];

        foreach (range($offset + 1, $offset + $count) as $number) {
            $item = NavigationMenuItem::query()->forceCreate([
                'navigation_menu_id' => $menu->getKey(),
                'label' => "Link {$number}",
                'type' => 'remote-url',
                'order_column' => 100 + $number,
            ]);
            $item->setMeta('url', "https://example.test/{$number}");
            $item->setTranslation('label', 'id', "Tautan {$number}");

            $child = NavigationMenuItem::query()->forceCreate([
                'navigation_menu_id' => $menu->getKey(),
                'parent_id' => $item->getKey(),
                'label' => "Child {$number}",
                'type' => 'remote-url',
                'order_column' => 1,
            ]);
            $child->setMeta('url', "https://example.test/{$number}/child");
            $child->setTranslation('label', 'id', "Anak {$number}");

            $items[] = $item;
        }

        return $items;
    }
}
