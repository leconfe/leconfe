<?php

namespace Tests\Feature;

use Tests\TestCase;

class TranslationCompletenessTest extends TestCase
{
    public function test_supported_locale_catalog_is_available(): void
    {
        $expectedLocales = explode(' ', 'ar az be bg bs bs_Latn ca ckb cs da de el en eo es es_MX et eu fa fa_AF fi fr fr_CA gd gl he hi hr hu hy id is it ja ka kk ko ky lo lt lv mk mn mr ms nb_NO nl nn pl ps pt pt_BR ro ru sk sl sq sr_Cyrl sr_Latn sv th tr uk ur uz uz_Latn vi zh_Hans zh_Hant');
        $configured = array_keys(config('app.locales'));
        sort($configured);

        $this->assertSame($expectedLocales, $configured);

        foreach ($configured as $locale) {
            $this->assertDirectoryExists(lang_path($locale));
        }
    }

    public function test_right_to_left_locales_have_panel_direction(): void
    {
        foreach (config('app.rtl_locales') as $locale) {
            $this->assertContains($locale, array_keys(config('app.locales')));
            $this->assertSame('rtl', __('filament-panels::layout.direction', [], $locale));
        }
    }

    public function test_regional_variants_reuse_available_panel_translations(): void
    {
        $aliases = [
            'bs_Latn' => 'bs',
            'es_MX' => 'es',
            'fa_AF' => 'fa',
            'fr_CA' => 'fr',
            'nb_NO' => 'no',
            'uz_Latn' => 'uz',
            'zh_Hans' => 'zh_CN',
            'zh_Hant' => 'zh_TW',
        ];

        foreach ($aliases as $locale => $parent) {
            $this->assertSame(
                __('filament-panels::layout.actions.logout.label', [], $parent),
                __('filament-panels::layout.actions.logout.label', [], $locale),
                "{$locale} should reuse {$parent} for panel text."
            );
        }
    }

    public function test_every_translation_directory_is_selectable(): void
    {
        $supportedLocales = array_keys(config('app.locales'));

        foreach (glob(lang_path('*'), GLOB_ONLYDIR) ?: [] as $directory) {
            if (basename($directory) === 'vendor') {
                continue;
            }

            $this->assertContains(basename($directory), $supportedLocales);
        }

        $this->assertContains('fr', $supportedLocales);
        $this->assertSame('Enregistrer', __('general.save', [], 'fr'));
    }

    public function test_every_supported_locale_includes_english_translation_keys(): void
    {
        foreach (config('app.locales') as $locale => $language) {
            foreach (['general', 'scheduled_conference', 'validation'] as $group) {
                $translationFile = lang_path("{$locale}/{$group}.php");

                $this->assertFileExists($translationFile, "Missing {$group} translations for {$language} ({$locale}).");

                $english = $this->flattenTranslations(require lang_path("en/{$group}.php"));
                $translations = $this->flattenTranslations(require $translationFile);

                $missing = [];
                $nonString = [];
                $empty = [];
                $placeholderMismatch = [];
                $branchPlaceholderMismatch = [];

                foreach ($english as $key => $value) {
                    if (! array_key_exists($key, $translations)) {
                        $missing[] = $key;

                        continue;
                    }

                    if (! is_string($translations[$key])) {
                        $nonString[] = $key;

                        continue;
                    }

                    if (trim($translations[$key]) === '') {
                        $empty[] = $key;
                    }

                    if ($this->placeholders($value) !== $this->placeholders($translations[$key])) {
                        $placeholderMismatch[] = $key;
                    }

                    $englishBranches = array_map($this->placeholders(...), $this->pluralBranches($value));

                    foreach ($this->pluralBranches($translations[$key]) as $branch) {
                        if (! in_array($this->placeholders($branch), $englishBranches, true)) {
                            $branchPlaceholderMismatch[] = $key;

                            break;
                        }
                    }
                }

                $this->assertSame([], $missing, "{$locale}/{$group} is missing keys: ".implode(', ', $missing));
                $this->assertSame([], $nonString, "{$locale}/{$group} has non-string keys: ".implode(', ', $nonString));
                $this->assertSame([], $empty, "{$locale}/{$group} has empty keys: ".implode(', ', $empty));
                $this->assertSame([], $placeholderMismatch, "{$locale}/{$group} changed placeholders in: ".implode(', ', $placeholderMismatch));
                $this->assertSame([], $branchPlaceholderMismatch, "{$locale}/{$group} has a plural form whose placeholders match no English form in: ".implode(', ', $branchPlaceholderMismatch));
            }
        }
    }

    public function test_new_locales_have_translated_text_and_preserve_markup(): void
    {
        $existing = ['ar', 'en', 'fr', 'id', 'ru', 'sq', 'uz'];
        foreach (array_keys(config('app.locales')) as $locale) {
            if (in_array($locale, $existing, true)) {
                continue;
            }

            $translatedCount = 0;

            foreach (['general', 'scheduled_conference', 'validation'] as $group) {
                $english = $this->flattenTranslations(require lang_path("en/{$group}.php"));
                $translations = $this->flattenTranslations(require lang_path("{$locale}/{$group}.php"));
                $markupMismatch = [];

                foreach ($english as $key => $source) {
                    $target = $translations[$key];

                    if ($group === 'general' && $target !== $source) {
                        $translatedCount++;
                    }

                    if ($this->markupTags($source) !== $this->markupTags($target)) {
                        $markupMismatch[] = $key;
                    }
                }

                $this->assertSame([], $markupMismatch, "{$locale}/{$group} changed HTML markup in: ".implode(', ', $markupMismatch));
            }

            $this->assertGreaterThan(200, $translatedCount, "{$locale} has too few translated application strings.");
        }
    }

    public function test_catalogs_use_real_line_breaks(): void
    {
        foreach (glob(lang_path('*/*.php')) ?: [] as $file) {
            $literal = array_keys(array_filter(
                $this->flattenTranslations(require $file),
                fn ($value) => is_string($value) && str_contains($value, '\\n'),
            ));

            $this->assertSame([], $literal, basename(dirname($file)).'/'.basename($file).' has a literal \\n in: '.implode(', ', $literal));
        }
    }

    private function flattenTranslations(array $translations, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($translations as $key => $value) {
            $path = $prefix.$key;

            if (is_array($value)) {
                $flattened += $this->flattenTranslations($value, $path.'.');
            } else {
                $flattened[$path] = $value;
            }
        }

        return $flattened;
    }

    private function placeholders(string $value): array
    {
        preg_match_all('/(?<![A-Za-z0-9_]):[A-Za-z][A-Za-z0-9_]*/', $value, $matches);

        $placeholders = array_values(array_unique($matches[0]));
        sort($placeholders);

        return $placeholders;
    }

    private function pluralBranches(string $value): array
    {
        return array_map(
            static fn (string $branch): string => preg_replace('/^[\{\[]([^\[\]\{\}]*)[\}\]]/', '', trim($branch)),
            explode('|', $value)
        );
    }

    private function markupTags(string $value): array
    {
        preg_match_all('/<\/?[a-z][^>]*>/i', $value, $matches);

        return array_map(
            static fn (string $tag): string => preg_replace('/\balt="[^"]*"/i', 'alt=""', $tag),
            $matches[0]
        );
    }
}
