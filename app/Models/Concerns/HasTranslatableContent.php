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
 * (Plank\Metable keys). The original column or meta value always holds the
 * default-language content, so code that does not know about translations keeps
 * working, and content saved before translations existed stays visible.
 */
trait HasTranslatableContent
{
    use HasTranslations;

    /**
     * Translations set before the model was saved.
     *
     * @var array<string, array<string, ?string>>
     */
    protected array $pendingTranslations = [];

    /**
     * Translations collected from form state, written by persistTranslatableState().
     *
     * @var array<string, array{values: array<string, ?string>, existingOnly: bool}>
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
     * The value for a locale, falling back to the default language and then to the
     * value stored in the original column or meta.
     */
    public function translated(string $key, ?string $locale = null): mixed
    {
        $this->guardAgainstNonTranslatableAttribute($key);

        $locales = array_unique([$locale ?? app()->getLocale(), $this->getDefaultContentLocale()]);

        foreach ($locales as $candidate) {
            $value = $this->getStoredTranslation($key, $candidate);

            if (! static::isBlankContent($value)) {
                return $value;
            }
        }

        return $this->getTranslationSource($key);
    }

    /**
     * The value stored in the `translate` table for a locale, without fallback.
     */
    public function getStoredTranslation(string $key, string $locale): ?string
    {
        if (! $this->exists) {
            return $this->pendingTranslations[$key][$locale] ?? null;
        }

        return $this->getTranslateRecord($key, $locale)?->getAttribute('value');
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

    public function setTranslation(string $key, string $locale, ?string $value): self
    {
        $this->guardAgainstNonTranslatableAttribute($key);

        if (! $this->exists) {
            $this->pendingTranslations[$key][$locale] = $value;

            return $this;
        }

        $this->writeTranslation($key, $locale, $value);

        if ($locale === $this->getDefaultContentLocale()) {
            $this->syncTranslationSource($key, $value);
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
     * Values for every enabled locale, default language first, as used by form state.
     *
     * @return array<string, ?string>
     */
    public function getTranslationsForForm(string $key): array
    {
        $default = $this->getDefaultContentLocale();

        return collect(app(ContentLocales::class)->ordered())
            ->mapWithKeys(function (string $locale) use ($key, $default) {
                $value = $this->getStoredTranslation($key, $locale);

                if ($locale === $default && static::isBlankContent($value)) {
                    $value = $this->getTranslationSource($key);
                }

                return [$locale => $value];
            })
            ->all();
    }

    /**
     * Replace the given translatable keys in form state with per-locale values when
     * more than one language is enabled.
     *
     * @param  array<int, string>  $formKeys  Form state paths such as `name` or `meta.about`.
     */
    public function fillTranslatableFormState(array $state, array $formKeys): array
    {
        if (! app(ContentLocales::class)->isMultilingual()) {
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
     * Put the default-language value of per-locale form state back in place so the
     * existing update and setManyMeta calls keep working, and remember the other
     * locales for persistTranslatableState().
     */
    public function syncTranslatableState(array $data): array
    {
        $default = $this->getDefaultContentLocale();
        $enabled = app(ContentLocales::class)->enabled();

        foreach ($this->getTranslatableAttributes() as $key) {
            $formKey = $this->getTranslatableFormKey($key);

            if (! Arr::has($data, $formKey)) {
                continue;
            }

            $value = Arr::get($data, $formKey);

            if (is_array($value)) {
                $this->pendingFormTranslations[$key] = [
                    'values' => Arr::only($value, $enabled),
                    'existingOnly' => false,
                ];

                Arr::set($data, $formKey, $value[$default] ?? null);

                continue;
            }

            // A single value is the default-language value; keep an existing row in step.
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

                $this->writeTranslation($key, $locale, is_scalar($value) ? (string) $value : null);
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

    protected function writeTranslation(string $key, string $locale, ?string $value): void
    {
        if (static::isBlankContent($value)) {
            $this->translate()->where('key', $key)->where('locale', $locale)->delete();

            return;
        }

        $this->translate()->updateOrCreate(
            ['key' => $key, 'locale' => $locale],
            ['value' => $value],
        );
    }

    protected function syncTranslationSource(string $key, ?string $value): void
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
     * Keep an existing default-language row in step with a column changed outside
     * the translation layer.
     */
    protected function syncDefaultTranslationsFromColumns(): void
    {
        $columns = array_filter($this->getTranslatableColumns(), fn (string $column) => $this->wasChanged($column));

        if (empty($columns) || ! $this->translate()->whereIn('key', $columns)->exists()) {
            return;
        }

        $default = $this->getDefaultContentLocale();
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
