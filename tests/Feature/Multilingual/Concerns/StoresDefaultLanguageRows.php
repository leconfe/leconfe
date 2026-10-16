<?php

namespace Tests\Feature\Multilingual\Concerns;

use App\Support\Multilingual\ContentLocales;
use Illuminate\Database\Eloquent\Model;

trait StoresDefaultLanguageRows
{
    /**
     * Store a default-language translation row without touching the original column
     * or meta, so the default-language fallback can be told apart from the legacy value.
     */
    protected function storeDefaultLanguageRow(Model $model, string $key, string $value): void
    {
        $model->translate()->create([
            'key' => $key,
            'locale' => app(ContentLocales::class)->default(),
            'value' => $value,
        ]);

        $model->refreshTranslationCache();
    }
}
