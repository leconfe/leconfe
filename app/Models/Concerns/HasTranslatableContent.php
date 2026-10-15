<?php

namespace App\Models\Concerns;

use App\Support\Multilingual\ContentLocales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use RahmanRamsi\LaravelTranslatable\HasTranslations;
use RahmanRamsi\LaravelTranslatable\Translate;

/**
 * Multi-language content stored in the `translate` table.
 *
 * Models declare `$translatableColumns` (database columns) and `$translatableMeta`
 * (Plank\Metable keys). Meta keys listed in `$translatableListMeta` hold a list of
 * strings per language, such as keywords.
 *
 * The original column or meta value always holds the primary-language content, so
 * code that does not know about translations keeps working, and content saved
 * before translations existed stays visible. The primary language is the site
 * default language unless the model overrides getPrimaryContentLocale(), for example
 * to use the language a paper is written in.
 */
trait HasTranslatableContent
{
    use HasTranslations;

    /**
     * Translations set before the model was saved.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $pendingTranslations = [];

    /**
     * Translations collected from form state, written by persistTranslatableState().
     *
     * @var array<string, array{values: array<string, mixed>, existingOnly: bool}>
     */
    protected array $pendingFormTranslations = [];

    public static function bootHasTranslatableContent(): void
    {
        static::saved(function (Model $model) {
            $model->syncDefaultTranslationsFromColumns();
            $model->flushPendingTranslations();
        });

        static::deleted(function (Model $model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->translate()->delete();
            $model->refreshTranslationCache();
        });
    }

    /**
     * Columns and meta keep their raw values; translations are read with translated().
     */
    public function getAttribute($key): mixed
    {
        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        return parent::setAttribute($key, $value);
    }

    public function fill(array $attributes)
    {
        return parent::fill($attributes);
    }

    public function getTranslatableAttributes(): array
    {
        return [...$this->getTranslatableColumns(), ...$this->getTranslatableMetaKeys()];
    }

    /**
     * @return array<int, string>
     */
    public function getTranslatableColumns(): array
    {
        return property_exists($this, 'translatableColumns') ? $this->translatableColumns : [];
    }

    /**
     * @return array<int, string>
     */
    public function getTranslatableMetaKeys(): array
    {
        return property_exists($this, 'translatableMeta') ? $this->translatableMeta : [];
    }

    /**
     * @return array<int, string>
     */
    public function getTranslatableListMetaKeys(): array
    {
        return property_exists($this, 'translatableListMeta') ? $this->translatableListMeta : [];
    }

    /**
     * Whether a key holds a list of strings per language rather than a single value.
     */
    public function isTranslatableList(string $key): bool
    {
        return in_array($key, $this->getTranslatableListMetaKeys(), true);
    }

    public function isTranslatableColumn(string $key): bool
    {
        return in_array($key, $this->getTranslatableColumns(), true);
    }

    /**
     * The form state path of a translatable key: `name` for a column, `meta.about` for meta.
     */
    public function getTranslatableFormKey(string $key): string
    {
        return $this->isTranslatableColumn($key) ? $key : 'meta.'.$key;
    }

    public function scopeWithTranslations(Builder $query): Builder
    {
        return $query->with('translate');
    }

    /**
     * The value for a locale, falling back to the primary language and then to the
     * value stored in the original column or meta.
     */
    public function translated(string $key, ?string $locale = null): mixed
    {
        $this->guardAgainstNonTranslatableAttribute($key);

        $resolved = $this->translatedLocale($key, $locale);

        $value = $resolved === null
            ? $this->getTranslationSource($key)
            : $this->getStoredTranslation($key, $resolved);

        return $this->isTranslatableList($key)
            ? $this->normalizeTranslationValue($key, $value) ?? []
            : $value;
    }

    /**
     * The locale whose stored value translated() returns, or null when it returns the
     * value of the original column or meta.
     */
    public function translatedLocale(string $key, ?string $locale = null): ?string
    {
        $this->guardAgainstNonTranslatableAttribute($key);

        $locales = array_unique([$locale ?? app()->getLocale(), $this->getPrimaryContentLocale()]);

        foreach ($locales as $candidate) {
            if (! static::isBlankContent($this->getStoredTranslation($key, $candidate))) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Stored values for the enabled languages other than the primary language, keyed
     * by locale. Blank values are left out.
     *
     * @return array<string, mixed>
     */
    public function getOtherTranslations(string $key): array
    {
        $this->guardAgainstNonTranslatableAttribute($key);

        $primary = $this->getPrimaryContentLocale();

        return collect(app(ContentLocales::class)->ordered())
            ->reject(fn (string $locale) => $locale === $primary)
            ->mapWithKeys(fn (string $locale) => [$locale => $this->getStoredTranslation($key, $locale)])
            ->reject(fn (mixed $value) => static::isBlankContent($value))
            ->all();
    }

    /**
     * The value stored in the `translate` table for a locale, without fallback. List
     * keys return an array.
     */
    public function getStoredTranslation(string $key, string $locale): mixed
    {
        if (! $this->exists) {
            return $this->pendingTranslations[$key][$locale] ?? null;
        }

        $value = $this->getTranslateRecord($key, $locale)?->getAttribute('value');

        if ($value === null || ! $this->isTranslatableList($key)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values($decoded) : [$value];
    }

    public function getTranslation(string $key, string $locale, bool $useFallbackLocale = true)
    {
        return $useFallbackLocale
            ? $this->translated($key, $locale)
            : $this->getStoredTranslation($key, $locale);
    }

    /**
     * The value in the original column or meta.
     */
    public function getTranslationSource(string $key): mixed
    {
        return $this->isTranslatableColumn($key)
            ? parent::getAttribute($key)
            : $this->getMeta($key);
    }

    public function setTranslation(string $key, string $locale, mixed $value): self
    {
        $this->guardAgainstNonTranslatableAttribute($key);

        if (! $this->exists) {
            $this->pendingTranslations[$key][$locale] = $value;

            return $this;
        }

        $this->writeTranslation($key, $locale, $value);

        if ($locale === $this->getPrimaryContentLocale()) {
            $this->syncTranslationSource($key, $this->normalizeTranslationValue($key, $value));
        }

        $this->refreshTranslationCache();

        return $this;
    }

    public function forgetTranslation(string $key, string $locale): self
    {
        $this->translate()->where('key', $key)->where('locale', $locale)->delete();
        $this->refreshTranslationCache();

        return $this;
    }

    /**
     * The languages a form shows for this model: the primary language first, then the
     * enabled languages. The primary language is kept even when it is not enabled.
     *
     * @return array<int, string>
     */
    public function getContentLocalesForForm(): array
    {
        return array_values(array_unique([$this->getPrimaryContentLocale(), ...app(ContentLocales::class)->ordered()]));
    }

    /**
     * Values for every form language, primary language first, as used by form state.
     *
     * @return array<string, mixed>
     */
    public function getTranslationsForForm(string $key): array
    {
        $primary = $this->getPrimaryContentLocale();
        $isList = $this->isTranslatableList($key);

        return collect($this->getContentLocalesForForm())
            ->mapWithKeys(function (string $locale) use ($key, $primary, $isList) {
                $value = $this->getStoredTranslation($key, $locale);

                if ($locale === $primary && static::isBlankContent($value)) {
                    $value = $this->getTranslationSource($key);
                }

                if ($isList) {
                    $value = $this->normalizeTranslationValue($key, $value) ?? [];
                }

                return [$locale => $value];
            })
            ->all();
    }

    /**
     * Replace the given translatable keys in form state with per-locale values when
     * the form shows more than one language.
     *
     * @param  array<int, string>  $formKeys  Form state paths such as `name` or `meta.about`.
     */
    public function fillTranslatableFormState(array $state, array $formKeys): array
    {
        if (count($this->getContentLocalesForForm()) < 2) {
            return $state;
        }

        foreach ($this->getTranslatableAttributes() as $key) {
            $formKey = $this->getTranslatableFormKey($key);

            if (in_array($formKey, $formKeys, true)) {
                Arr::set($state, $formKey, $this->getTranslationsForForm($key));
            }
        }

        return $state;
    }

    /**
     * Put the primary-language value of per-locale form state back in place so the
     * existing update and setManyMeta calls keep working, and remember the other
     * locales for persistTranslatableState().
     */
    public function syncTranslatableState(array $data): array
    {
        $default = $this->getPrimaryContentLocale();
        $enabled = array_values(array_unique([$default, ...app(ContentLocales::class)->enabled()]));

        foreach ($this->getTranslatableAttributes() as $key) {
            $formKey = $this->getTranslatableFormKey($key);

            if (! Arr::has($data, $formKey)) {
                continue;
            }

            $value = Arr::get($data, $formKey);
            $isList = $this->isTranslatableList($key);

            // A list key holds a list per locale, so a list itself is a single value.
            if (is_array($value) && ! ($isList && array_is_list($value))) {
                $this->pendingFormTranslations[$key] = [
                    'values' => Arr::only($value, $enabled),
                    'existingOnly' => false,
                ];

                Arr::set($data, $formKey, $this->normalizeTranslationValue($key, $value[$default] ?? null));

                continue;
            }

            if ($isList) {
                $value = $this->normalizeTranslationValue($key, $value);
                Arr::set($data, $formKey, $value);
            }

            // A single value is the primary-language value; keep an existing row in step.
            $this->pendingFormTranslations[$key] = [
                'values' => [$default => $value],
                'existingOnly' => true,
            ];
        }

        return $data;
    }

    public function persistTranslatableState(): void
    {
        if (empty($this->pendingFormTranslations)) {
            return;
        }

        foreach ($this->pendingFormTranslations as $key => $pending) {
            foreach ($pending['values'] as $locale => $value) {
                if ($pending['existingOnly'] && ! $this->translate()->where('key', $key)->where('locale', $locale)->exists()) {
                    continue;
                }

                $this->writeTranslation($key, $locale, $this->isTranslatableList($key) || is_scalar($value) ? $value : null);
            }
        }

        $this->pendingFormTranslations = [];
        $this->refreshTranslationCache();
    }

    /**
     * Copy every stored translation of this model onto another saved model, for
     * example a replicated record. Keys listed in $except are not copied.
     *
     * @param  array<int, string>  $except
     */
    public function copyTranslationsTo(Model $target, array $except = []): void
    {
        $this->translate()
            ->when($except, fn ($query) => $query->whereNotIn('key', $except))
            ->get()
            ->each(fn (Translate $translation) => $target->translate()->updateOrCreate(
                ['key' => $translation->key, 'locale' => $translation->locale],
                ['value' => $translation->value],
            ));

        $target->refreshTranslationCache();
    }

    public function refreshTranslationCache(): void
    {
        $this->indexedTranslateCollection = null;
        $this->unsetRelation('translate');

        if (method_exists($this, 'flushCache')) {
            $this->flushCache();
        }
    }

    public static function isBlankContent(mixed $value): bool
    {
        return ContentLocales::isBlank($value);
    }

    protected function getDefaultContentLocale(): string
    {
        return app(ContentLocales::class)->default();
    }

    /**
     * The language of the content in the original column or meta. Required form inputs,
     * the fallback of translated() and the original value follow this language.
     * Override it to give each record its own primary language.
     */
    public function getPrimaryContentLocale(): string
    {
        return $this->getDefaultContentLocale();
    }

    /**
     * A list key's value as a list of non-empty trimmed strings, or null when empty.
     * Other keys are returned unchanged.
     */
    protected function normalizeTranslationValue(string $key, mixed $value): mixed
    {
        if (! $this->isTranslatableList($key)) {
            return $value;
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        $items = collect(Arr::wrap($value))
            ->filter(fn (mixed $item) => is_scalar($item))
            ->map(fn (mixed $item) => trim((string) $item))
            ->filter(fn (string $item) => $item !== '')
            ->values()
            ->all();

        return $items ?: null;
    }

    protected function writeTranslation(string $key, string $locale, mixed $value): void
    {
        $value = $this->normalizeTranslationValue($key, $value);

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        } elseif ($value !== null && ! is_string($value)) {
            $value = is_scalar($value) ? (string) $value : null;
        }

        if (static::isBlankContent($value)) {
            $this->translate()->where('key', $key)->where('locale', $locale)->delete();

            return;
        }

        $this->translate()->updateOrCreate(
            ['key' => $key, 'locale' => $locale],
            ['value' => $value],
        );
    }

    protected function syncTranslationSource(string $key, mixed $value): void
    {
        if (! $this->isTranslatableColumn($key)) {
            $this->setMeta($key, $value);

            return;
        }

        parent::setAttribute($key, $value);

        if ($this->isDirty($key)) {
            $this->saveQuietly();
        }
    }

    protected function flushPendingTranslations(): void
    {
        $pending = $this->pendingTranslations;
        $this->pendingTranslations = [];

        foreach ($pending as $key => $values) {
            foreach ($values as $locale => $value) {
                $this->setTranslation($key, $locale, $value);
            }
        }
    }

    /**
     * Keep an existing primary-language row in step with a column changed outside
     * the translation layer.
     */
    protected function syncDefaultTranslationsFromColumns(): void
    {
        $columns = array_filter($this->getTranslatableColumns(), fn (string $column) => $this->wasChanged($column));

        if (empty($columns) || ! $this->translate()->whereIn('key', $columns)->exists()) {
            return;
        }

        $default = $this->getPrimaryContentLocale();
        $changed = false;

        foreach ($columns as $column) {
            $record = $this->translate()->where('key', $column)->where('locale', $default)->first();

            if ($record instanceof Translate) {
                $this->writeTranslation($column, $default, parent::getAttribute($column));
                $changed = true;
            }
        }

        if ($changed) {
            $this->refreshTranslationCache();
        }
    }
}
