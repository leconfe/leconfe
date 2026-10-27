<?php

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TranslationCatalogs;

require_once __DIR__.'/../../scripts/translations.php';

class TranslationCatalogsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/leconfe-translations-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/en', 0777, true);
        mkdir($this->directory.'/fr');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*/*') as $file) {
            unlink($file);
        }
        foreach (glob($this->directory.'/*', GLOB_ONLYDIR) as $directory) {
            rmdir($directory);
        }
        foreach (glob($this->directory.'/*.csv') as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function test_export_import_preserves_unicode_quotes_html_placeholders_actual_newlines_and_dotted_keys(): void
    {
        $english = ['nested' => ['message' => "<p>Hello :name's paper</p>\nNext line"], 'literal.key' => 'Save'];
        $french = ['nested' => ['message' => "<p>L’article de :name est prêt</p>\nLigne suivante"], 'literal.key' => 'Enregistrer'];
        $this->catalog('en', 'general', $english);
        $this->catalog('fr', 'general', $french);
        $catalogs = new TranslationCatalogs($this->directory);
        $catalogs->export('fr', $this->directory.'/export.csv');
        $catalogs->import('fr', $this->directory.'/export.csv');
        $this->assertSame($french, require $this->directory.'/fr/general.php');
        $this->assertStringContainsString("</p>\nLigne suivante", file_get_contents($this->directory.'/export.csv'));
        $this->assertStringNotContainsString('\\nLigne suivante', file_get_contents($this->directory.'/export.csv'));
    }

    public function test_export_discovers_all_english_catalogs_and_reports_missing_or_invalid_translations(): void
    {
        $this->catalog('en', 'general', ['save' => 'Save']);
        $this->catalog('en', 'custom_domain', ['message' => 'Hello :name']);
        $this->catalog('fr', 'custom_domain', ['message' => 'Bonjour :wrong']);
        $rows = (new TranslationCatalogs($this->directory))->rows('fr');
        $this->assertSame(['custom_domain', 'general'], array_column($rows, 1));
        $this->assertSame(['invalid_tokens', 'missing'], array_column($rows, 6));
    }

    public function test_import_updates_nested_and_literal_dotted_keys_and_accepts_utf8_bom(): void
    {
        $this->catalog('en', 'general', ['nested' => ['message' => '<p>Hello :name</p>'], 'literal.key' => 'Save']);
        $this->catalog('fr', 'general', ['nested' => ['message' => '<p>Ancien :name</p>'], 'literal.key' => 'Ancien']);
        $catalogs = new TranslationCatalogs($this->directory);
        $csv = $this->directory.'/export.csv';
        $catalogs->export('fr', $csv);
        file_put_contents($csv, "\xEF\xBB\xBF".str_replace('Ancien', 'Nouveau', file_get_contents($csv)));
        $catalogs->import('fr', $csv);
        $this->assertSame(['nested' => ['message' => '<p>Nouveau :name</p>'], 'literal.key' => 'Nouveau'], require $this->directory.'/fr/general.php');
    }

    public function test_import_keeps_the_catalog_layout_and_changes_only_edited_lines(): void
    {
        $this->catalog('en', 'general', ['save' => 'Save', 'nested' => ['open' => 'Open'], 'review' => "Reviewer's note"]);
        $french = "<?php\n\nreturn [\n    'save' => 'Enregistrer',\n    'nested' => [\n        'open' => 'Ouvrir',\n    ],\n    'review' => 'Note d\\'évaluateur',\n];\n";
        file_put_contents($this->directory.'/fr/general.php', $french);
        $catalogs = new TranslationCatalogs($this->directory);
        $csv = $this->directory.'/export.csv';
        $catalogs->export('fr', $csv);
        file_put_contents($csv, str_replace('Ouvrir', 'Ouvrir le fichier', file_get_contents($csv)));
        $catalogs->import('fr', $csv);
        $this->assertSame(str_replace('Ouvrir', 'Ouvrir le fichier', $french), file_get_contents($this->directory.'/fr/general.php'));
    }

    public function test_invalid_import_is_rejected_before_any_catalog_is_written(): void
    {
        $this->catalog('en', 'general', ['message' => '<p>Hello :name</p>']);
        $this->catalog('fr', 'general', ['message' => '<p>Bonjour :name</p>']);
        $before = file_get_contents($this->directory.'/fr/general.php');
        $catalogs = new TranslationCatalogs($this->directory);
        $csv = $this->directory.'/export.csv';
        $catalogs->export('fr', $csv);
        file_put_contents($csv, str_replace('<p>Bonjour :name</p>', '<b>Bonjour :wrong</b>', file_get_contents($csv)));
        try {
            $catalogs->import('fr', $csv);
            $this->fail('Expected invalid tokens to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Changed placeholders or HTML', $exception->getMessage());
            $this->assertSame($before, file_get_contents($this->directory.'/fr/general.php'));
        }
    }

    private function catalog(string $locale, string $name, array $values): void
    {
        file_put_contents($this->directory.'/'.$locale.'/'.$name.'.php', '<?php return '.var_export($values, true).';');
    }
}
