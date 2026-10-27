<?php

namespace App\Filament\Forms\Components;

use App\Support\Multilingual\ContentLocales;
use Filament\Forms\Components\Select;
use Illuminate\Validation\Rule;

/**
 * The language a user receives email in and that the interface switches to at sign-in.
 * Lists the enabled languages and is hidden while only one language is enabled.
 */
class PreferredLanguageSelect extends Select
{
    public static function make(string $name = 'locale'): static
    {
        return parent::make($name);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('general.preferred_language'))
            ->helperText(__('general.preferred_language_helper'))
            ->options(fn () => app(ContentLocales::class)->options())
            ->placeholder(fn () => app(ContentLocales::class)->label(app(ContentLocales::class)->default()))
            ->nullable()
            ->rule(fn () => Rule::in(app(ContentLocales::class)->enabled()))
            ->visible(fn () => app(ContentLocales::class)->isMultilingual());
    }
}
