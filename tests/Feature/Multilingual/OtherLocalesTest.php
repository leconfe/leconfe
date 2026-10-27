<?php

namespace Tests\Feature\Multilingual;

use App\Frontend\Conference\Pages\ProceedingDetail;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Proceeding;
use App\Panel\Conference\Livewire\MastHeadSetting;
use App\Panel\Conference\Resources\ProceedingResource\Pages\ManageProceedings;
use App\Providers\PanelProvider;
use DOMDocument;
use DOMXPath;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\Feature\Multilingual\Concerns\MakesPapers;
use Tests\TestCase;

class OtherLocalesTest extends TestCase
{
    use InteractsWithContentLanguages;
    use MakesPapers;
    use RefreshDatabase;

    /**
     * Keys whose value is legitimately the same as English in that locale: placeholder-only
     * patterns, and words the language borrows unchanged.
     */
    protected const SAME_AS_ENGLISH = [
        'ar' => ['translation_in'],
        'id' => ['translation_in', 'invoice_total', 'poster', 'admin', 'minor', 'patch'],
    ];

    public static function locales(): array
    {
        return [
            'Arabic' => ['ar', [
                'english' => 'الإنجليزية',
                'name' => 'العربية',
                'complete' => 'مكتملة',
                'missing' => 'مفقودة',
                'fallback' => 'اللغة البديلة: الإنجليزية',
                'show_translations' => 'إظهار الترجمات',
                'heading' => 'اللغة',
                'current' => 'اللغة الحالية:',
                'month' => 'أكتوبر',
                'dir' => 'rtl',
                'readiness_metadata' => 'الإنجليزية: لا توجد ترجمة لحقل العنوان. يرى القراء النص باللغة العربية.',
                'readiness_papers' => 'العربية: ورقة واحدة في هذه المداولات تحتوي على بيانات وصفية غير مترجمة. يراها القراء بلغة الورقة.',
            ]],
            'Indonesian' => ['id', [
                'english' => 'Inggris',
                'name' => 'Indonesia',
                'complete' => 'Lengkap',
                'missing' => 'Belum ada',
                'fallback' => 'Cadangan: Inggris',
                'show_translations' => 'Tampilkan terjemahan',
                'heading' => 'Bahasa',
                'current' => 'Bahasa saat ini:',
                'month' => 'Oktober',
                'dir' => 'ltr',
                'readiness_metadata' => 'Inggris: belum ada terjemahan untuk Judul. Pembaca melihat teks Indonesia.',
                'readiness_papers' => 'Indonesia: 1 makalah dalam prosiding ini memiliki metadata yang belum diterjemahkan. Pembaca melihatnya dalam bahasa makalah tersebut.',
            ]],
        ];
    }

    #[DataProvider('locales')]
    public function test_multilanguage_field_shows_statuses_and_language_names_in_the_interface_language(string $locale, array $expected): void
    {
        $conference = $this->makeConference();
        $conference->setMeta('about', '<p>About the conference</p>');
        $conference->setTranslations('about', ['en' => '<p>About the conference</p>', $locale => '<p>Translated about</p>']);
        $this->useConference($conference);
        $this->enableLanguages(['en', $locale]);
        $this->actingAs($this->makeAuthor());
        app()->setLocale($locale);

        $html = Livewire::test(MastHeadSetting::class)
            ->assertSeeHtml(Js::from($expected['show_translations'])->toHtml())
            ->assertDontSeeHtml(Js::from('Show translations')->toHtml())
            ->html();

        $this->assertSame([$expected['name'] => $expected['fallback']], $this->visibleStatuses($html, "formData.name.{$locale}"));
        $this->assertSame([$expected['name'] => $expected['complete']], $this->visibleStatuses($html, "formData.meta.about.{$locale}"));
        $this->assertSame([$expected['name'] => $expected['missing']], $this->visibleStatuses($html, "formData.meta.description.{$locale}"));
        $this->assertStringNotContainsString('French', $html);
        $this->assertStringNotContainsString('Fallback: English', $html);
    }

    #[DataProvider('locales')]
    public function test_language_sidebar_lists_languages_by_their_own_names_and_marks_the_current_one(string $locale, array $expected): void
    {
        $this->enableLanguages(['en', $locale]);
        app()->getSite()->setMeta('sidebars', ['language']);

        $html = $this->withoutVite()
            ->withSession(['locale' => $locale])
            ->get(route('livewirePageGroup.website.pages.home'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression("/<html[^>]*dir=\"{$expected['dir']}\"/", $html);
        $this->assertSame(1, preg_match('/<div\s+id="sidebar-language".*?<\/nav>/s', $html, $match));
        $block = $match[0];

        $this->assertStringContainsString("<h2 class=\"text-heading\">{$expected['heading']}</h2>", $block);
        $this->assertStringContainsString("<span class=\"sr-only\">{$expected['current']}</span>", $block);
        $this->assertStringContainsString("<span lang=\"{$locale}\">{$expected['name']}</span>", $block);
        $this->assertStringContainsString('<span lang="en">English</span>', $block);
        $this->assertSame(1, substr_count($block, 'aria-current="true"'));
        $this->assertMatchesRegularExpression("/hreflang=\"{$locale}\"\s+aria-current=\"true\"/", $block);
    }

    #[DataProvider('locales')]
    public function test_public_paper_and_proceeding_dates_use_localized_month_names(string $locale, array $expected): void
    {
        $conference = $this->makeConference();
        $this->useConference($conference, $this->makeScheduledConference($conference));
        $this->enableLanguages(['en', $locale]);

        $proceeding = $this->makeProceeding();
        $proceeding->update(['published_at' => Carbon::parse('2026-10-22')]);
        $paper = $this->makePaper($this->makeAuthor(), 'en', [
            'title' => ['en' => 'Coral reefs'],
        ], SubmissionStage::Proceeding, SubmissionStatus::Published, $proceeding);
        $paper->update(['published_at' => Carbon::parse('2026-10-22')]);

        $this->visitPaper($conference, $paper, $locale)
            ->assertSee($expected['month'])
            ->assertDontSee('October');

        $this->withoutVite()
            ->withSession(['locale' => $locale])
            ->get(route(ProceedingDetail::getRouteName('conference'), [
                'conference' => $conference->path,
                'proceeding' => $proceeding->getKey(),
            ]))
            ->assertOk()
            ->assertSee($expected['month'])
            ->assertDontSee('October');
    }

    #[DataProvider('locales')]
    public function test_proceeding_publication_readiness_summary_is_in_the_interface_language(string $locale, array $expected): void
    {
        $conference = $this->makeConference();
        $this->useConference($conference, $this->makeScheduledConference($conference));
        $this->enableLanguages(['en', $locale], default: $locale);
        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_CONFERENCE));
        Gate::before(fn () => true);
        $author = $this->makeAuthor();
        $this->actingAs($author);
        app()->setLocale($locale);

        $proceeding = Proceeding::query()->create([
            'title' => 'Proceedings 2026',
            'volume' => 1,
            'year' => 2026,
            'published' => false,
        ]);
        $this->makePaper($author, 'en', [
            'title' => ['en' => 'Coral reefs'],
            'abstract' => ['en' => '<p>A study of coral reefs</p>'],
        ], SubmissionStage::Editing, SubmissionStatus::Editing, $proceeding);

        $html = Livewire::test(ManageProceedings::class)
            ->mountTableAction('publish', $proceeding)
            ->assertSeeHtml('data-language-readiness')
            ->html();

        $this->assertStringContainsString($expected['readiness_metadata'], $html);
        $this->assertStringContainsString($expected['readiness_papers'], $html);
        $this->assertStringNotContainsString('no translation for', $html);
        $this->assertStringNotContainsString('in this proceeding', $html);
    }

    #[DataProvider('locales')]
    public function test_every_string_the_language_work_added_is_translated(string $locale): void
    {
        $english = require lang_path('en/general.php');
        $translations = require lang_path("{$locale}/general.php");

        $keys = array_slice(array_keys($english), array_search('language', array_keys($english), true));
        $keys[] = 'languages_english_fallback';

        $untranslated = array_values(array_filter(
            array_diff($keys, self::SAME_AS_ENGLISH[$locale]),
            fn (string $key) => ! is_string($translations[$key] ?? null)
                || trim($translations[$key]) === ''
                || $translations[$key] === $english[$key],
        ));

        $this->assertGreaterThan(150, count($keys));
        $this->assertSame([], $untranslated, "Untranslated {$locale} strings.");
    }

    /**
     * @return array<string, string>
     */
    protected function visibleStatuses(string $html, string $translationPath): array
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        $xpath = new DOMXPath($document);

        $field = $xpath->query("//*[contains(@class, 'fi-fo-multilanguage-field')][contains(@x-data, '{$translationPath}')]")->item(0);
        $this->assertNotNull($field, "Multilanguage field for {$translationPath} is rendered.");

        $statuses = [];
        foreach ($xpath->query('.//*[@data-multilanguage-status]/li', $field) as $item) {
            $visible = $xpath->query('./*[@data-status][not(contains(@style, "display: none"))]', $item);
            $this->assertCount(1, $visible);
            $statuses[trim($item->firstElementChild->textContent)] = trim($visible->item(0)->textContent);
        }

        return $statuses;
    }
}
