<?php

namespace Tests\Feature;

use Tests\TestCase;

class TranslationCompletenessTest extends TestCase
{
    private const REQUIRED_GENERAL_TRANSLATION_KEYS = [
        'uploaded_at',
    ];

    public function test_all_general_translations_include_required_keys(): void
    {
        $translationFiles = glob(base_path('lang/*/general.php')) ?: [];

        $this->assertNotEmpty($translationFiles);

        foreach ($translationFiles as $translationFile) {
            $translations = require $translationFile;

            foreach (self::REQUIRED_GENERAL_TRANSLATION_KEYS as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $translations,
                    sprintf('%s is missing the %s translation key.', $translationFile, $key)
                );
            }
        }
    }
}
