<?php

namespace Tests\Feature\Multilingual;

use App\Facades\Setting;
use App\Models\Conference;
use App\Models\ScheduledConference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RahmanRamsi\LaravelTranslatable\Translate;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class TranslationLayerTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableLanguages(['en', 'id', 'fr']);
    }

    public function test_translatable_table_exists_with_the_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('translate'));
        $this->assertTrue(Schema::hasColumns('translate', [
            'id', 'translatable_type', 'translatable_id', 'locale', 'key', 'value',
        ]));
    }

    public function test_column_uses_current_locale_then_default_language(): void
    {
        $conference = $this->makeConference('Annual Conference');
        $conference->setTranslation('name', 'id', 'Konferensi Tahunan');

        app()->setLocale('id');
        $this->assertSame('Konferensi Tahunan', $conference->translated('name'));

        app()->setLocale('fr');
        $this->assertSame('Annual Conference', $conference->translated('name'));

        $scheduledConference = $this->makeScheduledConference($conference, 'Annual Meeting 2026');
        $scheduledConference->setTranslations('title', [
            'en' => 'Annual Meeting 2026 (EN)',
            'id' => 'Pertemuan Tahunan 2026',
        ]);

        $this->assertSame('Pertemuan Tahunan 2026', $scheduledConference->translated('title', 'id'));
        $this->assertSame('Annual Meeting 2026 (EN)', $scheduledConference->translated('title', 'fr'));
    }

    public function test_default_language_row_is_used_before_the_legacy_value(): void
    {
        $this->enableLanguages(['en', 'id', 'fr'], 'id');

        $conference = $this->makeConference('Annual Conference');
        $conference->translate()->create(['key' => 'name', 'locale' => 'id', 'value' => 'Konferensi Tahunan']);
        $conference->setMeta('about', '<p>Legacy about</p>');
        $conference->translate()->create(['key' => 'about', 'locale' => 'id', 'value' => '<p>Tentang</p>']);
        $conference->refreshTranslationCache();

        $this->assertSame('Konferensi Tahunan', $conference->translated('name', 'fr'));
        $this->assertSame('<p>Tentang</p>', $conference->translated('about', 'fr'));
    }

    public function test_meta_uses_current_locale_then_default_language(): void
    {
        $conference = $this->makeConference();
        $conference->setTranslations('about', [
            'en' => '<p>About us</p>',
            'id' => '<p>Tentang kami</p>',
        ]);

        $this->assertSame('<p>Tentang kami</p>', $conference->translated('about', 'id'));
        $this->assertSame('<p>About us</p>', $conference->translated('about', 'fr'));
    }

    public function test_legacy_column_and_meta_values_are_shown_without_translation_rows(): void
    {
        $conference = $this->makeConference('Legacy Conference');
        $conference->setMeta('about', '<p>Legacy about</p>');
        $scheduledConference = $this->makeScheduledConference($conference, 'Legacy Scheduled Conference');
        $scheduledConference->setMeta('about', '<p>Legacy scheduled about</p>');

        $this->assertSame(0, Translate::query()->count());

        foreach (['en', 'id', 'fr'] as $locale) {
            $this->assertSame('Legacy Conference', $conference->translated('name', $locale));
            $this->assertSame('<p>Legacy about</p>', $conference->translated('about', $locale));
            $this->assertSame('Legacy Scheduled Conference', $scheduledConference->translated('title', $locale));
            $this->assertSame('<p>Legacy scheduled about</p>', $scheduledConference->translated('about', $locale));
        }

        $this->assertSame('Legacy Conference', $conference->name);
        $this->assertSame('<p>Legacy about</p>', $conference->getMeta('about'));
    }

    public function test_empty_rich_text_falls_back(): void
    {
        $conference = $this->makeConference('Annual Conference');
        $conference->setMeta('about', '<p>About us</p>');

        $conference->translate()->create(['key' => 'about', 'locale' => 'id', 'value' => '<p>&nbsp;</p>']);
        $conference->translate()->create(['key' => 'name', 'locale' => 'id', 'value' => '   ']);
        $conference->refreshTranslationCache();

        $this->assertSame('<p>About us</p>', $conference->translated('about', 'id'));
        $this->assertSame('Annual Conference', $conference->translated('name', 'id'));

        $conference->setTranslation('about', 'id', '<p></p>');

        $this->assertFalse($conference->translate()->where('key', 'about')->where('locale', 'id')->exists());
        $this->assertSame('<p>About us</p>', $conference->translated('about', 'id'));
    }

    public function test_rich_text_with_only_media_is_not_empty(): void
    {
        $conference = $this->makeConference();
        $conference->setTranslation('about', 'id', '<p><img src="/logo.png" alt=""></p>');

        $this->assertSame('<p><img src="/logo.png" alt=""></p>', $conference->translated('about', 'id'));
    }

    public function test_saving_the_default_language_syncs_the_column_and_meta(): void
    {
        $conference = $this->makeConference('Old name');
        $conference->setTranslation('name', 'en', 'New name');
        $conference->setTranslation('about', 'en', '<p>New about</p>');

        $fresh = Conference::query()->find($conference->getKey());
        $this->assertSame('New name', $fresh->name);
        $this->assertSame('<p>New about</p>', $fresh->getMeta('about'));

        $conference->setTranslation('name', 'id', 'Nama baru');
        $this->assertSame('New name', Conference::query()->find($conference->getKey())->name);

        $scheduledConference = $this->makeScheduledConference($conference, 'Old title');
        $scheduledConference->setTranslation('title', 'en', 'New title');
        $this->assertSame('New title', ScheduledConference::query()->find($scheduledConference->getKey())->title);
    }

    public function test_column_changed_outside_the_layer_updates_the_default_language_row(): void
    {
        $conference = $this->makeConference('First name');
        $conference->setTranslations('name', ['en' => 'First name', 'id' => 'Nama pertama']);

        $conference->update(['name' => 'Renamed']);

        $this->assertSame('Renamed', $conference->getStoredTranslation('name', 'en'));
        $this->assertSame('Renamed', $conference->translated('name', 'fr'));
        $this->assertSame('Nama pertama', $conference->translated('name', 'id'));
    }

    public function test_translations_on_an_unsaved_model_persist_after_saving(): void
    {
        $conference = new Conference(['name' => 'Draft Conference', 'path' => 'draft']);
        $conference->setTranslation('name', 'id', 'Konferensi Draf');
        $conference->setTranslation('about', 'id', '<p>Tentang draf</p>');

        $this->assertSame('Konferensi Draf', $conference->translated('name', 'id'));
        $this->assertSame(0, Translate::query()->count());

        $conference->save();

        $this->assertSame(2, Translate::query()->count());

        $fresh = Conference::query()->find($conference->getKey());
        $this->assertSame('Konferensi Draf', $fresh->translated('name', 'id'));
        $this->assertSame('<p>Tentang draf</p>', $fresh->translated('about', 'id'));
        $this->assertSame('Draft Conference', $fresh->translated('name', 'fr'));
    }

    public function test_reading_after_a_write_returns_the_new_value(): void
    {
        $conference = $this->makeConference();
        $conference->setTranslation('name', 'id', 'Pertama');
        $this->assertSame('Pertama', $conference->translated('name', 'id'));

        $cached = Conference::query()->withTranslations()->find($conference->getKey());
        $this->assertSame('Pertama', $cached->translated('name', 'id'));

        $conference->setTranslation('name', 'id', 'Kedua');
        $this->assertSame('Kedua', $conference->translated('name', 'id'));
        $this->assertSame('Kedua', Conference::query()->withTranslations()->find($conference->getKey())->translated('name', 'id'));
        $this->assertSame('Kedua', Conference::query()->find($conference->getKey())->translated('name', 'id'));
        $this->assertSame(['id' => 'Kedua'], $conference->getTranslations('name'));
    }

    public function test_deleting_a_model_removes_its_translations(): void
    {
        $conference = $this->makeConference();
        $conference->setTranslation('name', 'id', 'Konferensi');
        $scheduledConference = $this->makeScheduledConference($conference);
        $scheduledConference->setTranslation('title', 'id', 'Jadwal');

        $otherConference = $this->makeConference('Other', 'other');
        $otherConference->setTranslation('name', 'id', 'Lainnya');

        $scheduledConference->delete();
        $this->assertSame(1, $scheduledConference->translate()->count(), 'Soft-deleted models keep their translations.');

        $scheduledConference->forceDelete();
        $this->assertSame(0, Translate::query()->where('translatable_type', $scheduledConference->getMorphClass())->count());

        $conference->delete();
        $this->assertSame(0, Translate::query()->where('translatable_id', $conference->getKey())->where('translatable_type', $conference->getMorphClass())->count());
        $this->assertSame(1, Translate::query()->count());
    }

    public function test_disabling_a_language_keeps_its_translations(): void
    {
        $conference = $this->makeConference();
        $conference->setTranslation('name', 'id', 'Konferensi');

        Setting::update(['languages' => ['en'], 'default_language' => 'en']);
        $this->enableLanguages(['en']);

        $this->assertSame(1, Translate::query()->where('locale', 'id')->count());
        $this->assertSame('Konferensi', $conference->translated('name', 'id'));

        $this->enableLanguages(['en', 'id']);
        app()->setLocale('id');
        $this->assertSame('Konferensi', Conference::query()->find($conference->getKey())->translated('name'));
    }

    public function test_site_meta_is_translatable(): void
    {
        $site = app()->getSite();
        $site->setMeta('name', 'Conference Portal');
        $site->setTranslation('name', 'id', 'Portal Konferensi');

        $this->assertSame('Portal Konferensi', $site->translated('name', 'id'));
        $this->assertSame('Conference Portal', $site->translated('name', 'fr'));
    }
}
