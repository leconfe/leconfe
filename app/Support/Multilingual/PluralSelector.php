<?php

namespace App\Support\Multilingual;

use Illuminate\Translation\MessageSelector;

/**
 * Laravel knows plural rules only for plain languages and some regional locales.
 * Any other locale, such as sr_Latn or fa_AF, gets the single-form rule, so every
 * count picks the first form. Those locales use their language's rule instead.
 */
class PluralSelector extends MessageSelector
{
    public function getPluralIndex($locale, $number)
    {
        $language = strstr($locale, '_', true);

        if ($language !== false && parent::getPluralIndex($locale, 1) === parent::getPluralIndex($locale, 2)) {
            return parent::getPluralIndex($language, $number);
        }

        return parent::getPluralIndex($locale, $number);
    }
}
