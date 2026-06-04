<?php

namespace Tests\Feature;

use Tests\TestCase;

class TranslationCompletenessTest extends TestCase
{
    public function test_all_general_translations_include_submission_file_upload_date_label(): void
    {
        $translationFiles = glob(base_path('lang/*/general.php')) ?: [];

        $this->assertNotEmpty($translationFiles);

        foreach ($translationFiles as $translationFile) {
            $translations = require $translationFile;

            $this->assertArrayHasKey(
                'uploaded_at',
                $translations,
                sprintf('%s is missing the uploaded_at translation key.', $translationFile)
            );
        }
    }
}
