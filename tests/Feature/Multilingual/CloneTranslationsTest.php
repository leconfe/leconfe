<?php

namespace Tests\Feature\Multilingual;

use App\Actions\Conferences\ConferenceCloneAction;
use App\Models\Conference;
use App\Models\Scopes\ConferenceScope;
use App\Models\StaticPage;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RahmanRamsi\LaravelTranslatable\Translate;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class CloneTranslationsTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected Conference $source;

    protected Topic $topic;

    protected StaticPage $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableLanguages(['en', 'id']);

        $this->source = $this->makeConference('Annual Conference', 'annual');
        $this->source->setMeta('about', '<p>About us</p>');
        $this->source->setTranslation('name', 'id', 'Konferensi Tahunan');
        $this->source->setTranslation('about', 'id', '<p>Tentang kami</p>');

        $this->useConference($this->source);

        $this->topic = Topic::query()->create(['name' => 'Linguistics']);
        $this->topic->setTranslation('name', 'id', 'Linguistik');

        $this->page = StaticPage::query()->create(['title' => 'Venue', 'slug' => 'venue']);
        $this->page->setMeta('content', '<p>Main hall</p>');
        $this->page->setTranslation('title', 'id', 'Tempat');
        $this->page->setTranslation('content', 'id', '<p>Aula utama</p>');

        app()->setCurrentConferenceId(0);
    }

    public function test_clone_keeps_the_translations_of_the_conference_topics_and_static_pages(): void
    {
        $sourceRows = $this->translationRows();

        $clone = ConferenceCloneAction::run([
            'conference_id' => $this->source->getKey(),
            'name' => 'Annual Conference',
            'path' => 'annual',
        ]);

        $clone = Conference::query()->findOrFail($clone->getKey());
        $clonedTopic = Topic::withoutGlobalScopes([ConferenceScope::class])->where('conference_id', $clone->getKey())->sole();
        $clonedPage = StaticPage::withoutGlobalScopes([ConferenceScope::class])->where('conference_id', $clone->getKey())->sole();

        app()->setLocale('id');

        $this->assertSame('Konferensi Tahunan', $clone->translated('name'));
        $this->assertSame('<p>Tentang kami</p>', $clone->translated('about'));
        $this->assertSame('Linguistik', $clonedTopic->translated('name'));
        $this->assertSame('Tempat', $clonedPage->translated('title'));
        $this->assertSame('<p>Aula utama</p>', $clonedPage->translated('content'));

        app()->setLocale('en');

        $this->assertSame('Annual Conference', $clone->translated('name'));
        $this->assertSame('<p>Main hall</p>', $clonedPage->translated('content'));

        $this->assertSame($sourceRows, $this->translationRows([
            [Conference::class, $this->source->getKey()],
            [Topic::class, $this->topic->getKey()],
            [StaticPage::class, $this->page->getKey()],
        ]));

        $this->assertSame(2, $clone->translate()->count());
        $this->assertSame(1, $clonedTopic->translate()->count());
        $this->assertSame(2, $clonedPage->translate()->count());
        $this->assertSame(10, Translate::query()->count());
    }

    public function test_a_clone_with_a_new_name_keeps_the_other_language_names_of_the_source(): void
    {
        $sourceRows = $this->translationRows();

        $clone = ConferenceCloneAction::run([
            'conference_id' => $this->source->getKey(),
            'name' => 'Regional Workshop',
            'path' => 'workshop',
        ]);

        $clone = Conference::query()->findOrFail($clone->getKey());

        $this->assertSame('Regional Workshop', $clone->name);
        $this->assertSame('Regional Workshop', $clone->translated('name', 'en'));
        $this->assertSame('Konferensi Tahunan', $clone->translated('name', 'id'));
        $this->assertSame('<p>Tentang kami</p>', $clone->translated('about', 'id'));
        $this->assertNull($clone->getStoredTranslation('name', 'en'));

        $this->assertSame(
            [['about', 'id', '<p>Tentang kami</p>'], ['name', 'id', 'Konferensi Tahunan']],
            $this->clonedRows($clone),
        );

        $this->assertSame($sourceRows, $this->translationRows([
            [Conference::class, $this->source->getKey()],
            [Topic::class, $this->topic->getKey()],
            [StaticPage::class, $this->page->getKey()],
        ]));
        $this->assertSame('Annual Conference', $this->source->fresh()->name);
        $this->assertSame('Konferensi Tahunan', $this->source->fresh()->translated('name', 'id'));
    }

    public function test_a_new_name_replaces_the_copied_default_language_name_of_the_clone(): void
    {
        $this->source->setTranslation('name', 'en', 'Annual Conference');
        $sourceRows = $this->translationRows();

        $clone = ConferenceCloneAction::run([
            'conference_id' => $this->source->getKey(),
            'name' => 'Regional Workshop',
            'path' => 'workshop',
        ]);

        $clone = Conference::query()->findOrFail($clone->getKey());

        $this->assertSame('Regional Workshop', $clone->name);
        $this->assertSame('Regional Workshop', $clone->getStoredTranslation('name', 'en'));
        $this->assertSame('Konferensi Tahunan', $clone->translated('name', 'id'));

        $this->assertSame(
            [['about', 'id', '<p>Tentang kami</p>'], ['name', 'en', 'Regional Workshop'], ['name', 'id', 'Konferensi Tahunan']],
            $this->clonedRows($clone),
        );

        $this->assertSame($sourceRows, $this->translationRows([
            [Conference::class, $this->source->getKey()],
            [Topic::class, $this->topic->getKey()],
            [StaticPage::class, $this->page->getKey()],
        ]));
        $this->assertSame('Annual Conference', $this->source->fresh()->getStoredTranslation('name', 'en'));
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    protected function clonedRows(Conference $clone): array
    {
        return Translate::query()
            ->where('translatable_type', $clone->getMorphClass())
            ->where('translatable_id', $clone->getKey())
            ->orderBy('key')
            ->orderBy('locale')
            ->get(['key', 'locale', 'value'])
            ->map(fn (Translate $row) => [$row->key, $row->locale, $row->value])
            ->all();
    }

    /**
     * @param  array<int, array{0: class-string, 1: int}>|null  $owners
     * @return array<int, array<string, mixed>>
     */
    protected function translationRows(?array $owners = null): array
    {
        return Translate::query()
            ->when($owners, function ($query) use ($owners) {
                $query->where(function ($query) use ($owners) {
                    foreach ($owners as [$type, $id]) {
                        $query->orWhere(fn ($query) => $query
                            ->where('translatable_type', (new $type)->getMorphClass())
                            ->where('translatable_id', $id));
                    }
                });
            })
            ->orderBy('id')
            ->get(['id', 'translatable_type', 'translatable_id', 'locale', 'key', 'value'])
            ->toArray();
    }
}
