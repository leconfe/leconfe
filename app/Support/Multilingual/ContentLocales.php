<?php

namespace App\Support\Multilingual;

use Illuminate\Support\Facades\Schema;

/**
 * Resolves the enabled content languages.
 *
 * A conference has its own language settings. A conference that has not saved them
 * uses the site languages. With no language enabled, English is used.
 */
class ContentLocales
{
    protected const STORAGE_READY = 'leconfe.multilingual.storage-ready';

    /**
     * Whether the `translate` table exists. It does not exist on an installation that
     * has not run the upgrade yet; translation reads then fall back to stored values.
     * Only a positive result is remembered, so the table is picked up once migrated.
     */
    public static function hasTranslationStorage(): bool
    {
        $app = app();

        if ($app->bound(static::STORAGE_READY)) {
            return true;
        }

        try {
            $ready = Schema::hasTable('translate');
        } catch (\Throwable) {
            return false;
        }

        if ($ready) {
            $app->instance(static::STORAGE_READY, true);
        }

        return $ready;
    }

    /**
     * Enabled languages that the application supports.
     *
     * @return array<int, string>
     */
    public function enabled(): array
    {
        $enabled = array_values(array_intersect(
            (array) $this->setting('languages', ['en']),
            array_keys(config('app.locales', []))
        ));

        return $enabled ?: ['en'];
    }

    public function default(): string
    {
        $enabled = $this->enabled();
        $default = $this->setting('default_language', 'en');

        return in_array($default, $enabled, true) ? $default : $enabled[0];
    }

    protected function setting(string $key, mixed $default): mixed
    {
        $conference = app()->getCurrentConferenceId() ? app()->getCurrentConference() : null;

        if ($conference?->hasMeta("settings_{$key}")) {
            return $conference->getMeta("settings_{$key}") ?? $default;
        }

        return app()->getSite()->getMeta("settings_{$key}") ?? $default;
    }

    /**
     * Enabled languages with the default language first.
     *
     * @return array<int, string>
     */
    public function ordered(): array
    {
        $default = $this->default();

        return array_values(array_unique([$default, ...$this->enabled()]));
    }

    public function isEnabled(?string $locale): bool
    {
        return is_string($locale) && in_array($locale, $this->enabled(), true);
    }

    public function isMultilingual(): bool
    {
        return count($this->enabled()) > 1;
    }

    public function isRtl(?string $locale = null): bool
    {
        return in_array($locale ?? app()->getLocale(), config('app.rtl_locales', []), true);
    }

    public function direction(?string $locale = null): string
    {
        return $this->isRtl($locale) ? 'rtl' : 'ltr';
    }

    public function label(string $locale): string
    {
        return config("app.locales.{$locale}", $locale);
    }

    /**
     * The language's name in its own language, such as "Français" for `fr`, falling
     * back to its configured label.
     */
    public function autonym(string $locale): string
    {
        $name = class_exists(\Locale::class) ? \Locale::getDisplayName($locale, $locale) : '';

        if ($name === '' || $name === $locale) {
            return $this->label($locale);
        }

        return mb_convert_case(mb_substr($name, 0, 1), MB_CASE_TITLE).mb_substr($name, 1);
    }

    /**
     * Enabled languages keyed by locale with their labels, default language first.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        return collect($this->ordered())
            ->mapWithKeys(fn (string $locale) => [$locale => $this->label($locale)])
            ->all();
    }

    /**
     * Whether a value has no content. Empty rich text such as `<p></p>` counts as blank.
     */
    public static function isBlank(mixed $value): bool
    {
        if (! is_string($value)) {
            return blank($value);
        }

        if (preg_match('/<(img|iframe|video|audio|embed|object|svg|picture|table|hr)\b/i', $value)) {
            return false;
        }

        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $text)) === '';
    }
}
