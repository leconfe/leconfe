<?php

namespace Tests\Feature\Multilingual;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PluralFormsTest extends TestCase
{
    public static function countedPapers(): array
    {
        return [
            'pl 1' => ['pl', 1, '1 rękopis w tym tomie ma'],
            'pl 2' => ['pl', 2, '2 rękopisy w tym tomie mają'],
            'pl 5' => ['pl', 5, '5 rękopisów w tym tomie ma'],
            'pl 12' => ['pl', 12, '12 rękopisów w tym tomie ma'],
            'pl 22' => ['pl', 22, '22 rękopisy w tym tomie mają'],
            'uk 1' => ['uk', 1, '1 рукопис у цьому томі має'],
            'uk 2' => ['uk', 2, '2 рукописи в цьому томі мають'],
            'uk 5' => ['uk', 5, '5 рукописів у цьому томі мають'],
            'uk 21' => ['uk', 21, '21 рукопис у цьому томі має'],
            'uk 22' => ['uk', 22, '22 рукописи в цьому томі мають'],
            'sr_Latn 2' => ['sr_Latn', 2, '2 rada u ovom zborniku imaju'],
            'sr_Latn 5' => ['sr_Latn', 5, '5 radova u ovom zborniku ima'],
            'sr_Latn 21' => ['sr_Latn', 21, '21 rad u ovom zborniku ima'],
            'sr_Cyrl 22' => ['sr_Cyrl', 22, '22 рада у овом зборнику имају'],
            'bs_Latn 25' => ['bs_Latn', 25, '25 rukopisa u ovom zborniku radova ima'],
            'sl 101' => ['sl', 101, '101 prispevek v tem zborniku ima'],
            'sl 3' => ['sl', 3, '3 prispevki v tem zborniku imajo'],
        ];
    }

    #[DataProvider('countedPapers')]
    public function test_incomplete_paper_count_uses_the_language_plural_form(string $locale, int $count, string $expected): void
    {
        $this->assertStringContainsString(
            $expected,
            trans_choice('general.language_readiness_papers_incomplete', $count, ['language' => 'English', 'incomplete' => $count], $locale)
        );
    }
}
