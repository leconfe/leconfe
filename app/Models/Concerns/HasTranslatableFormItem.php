<?php

namespace App\Models\Concerns;

use Illuminate\Support\Arr;

/**
 * A custom form item whose name, description and response options are entered per
 * language. Models declare `$translatableMeta = ['name', 'description', 'response_options']`
 * and `$translatableListMeta = ['response_options']`.
 *
 * Answers always store the primary-language option. A translated option is matched
 * to its primary-language option by position.
 */
trait HasTranslatableFormItem
{
    use HasTranslatableContent {
        normalizeTranslationValue as normalizeContentTranslationValue;
    }

    /**
     * The primary-language response options.
     *
     * @return array<int, string>
     */
    public function getPrimaryResponseOptions(): array
    {
        return array_values($this->getIndexedPrimaryResponseOptions());
    }

    /**
     * The response option labels in the given language, or the current language, in
     * the order of the primary-language options. An option without a translation
     * falls back to the primary-language option.
     *
     * @return array<int, string>
     */
    public function translatedResponseOptions(?string $locale = null): array
    {
        $primary = $this->getPrimaryContentLocale();
        $localized = $this->getStoredTranslation('response_options', $locale ?? app()->getLocale()) ?? [];
        $primaryLocalized = $this->getStoredTranslation('response_options', $primary) ?? [];

        return collect($this->getIndexedPrimaryResponseOptions())
            ->map(function (string $option, int $index) use ($localized, $primaryLocalized) {
                foreach ([$localized[$index] ?? null, $primaryLocalized[$index] ?? null] as $label) {
                    if (! static::isBlankContent($label)) {
                        return (string) $label;
                    }
                }

                return $option;
            })
            ->values()
            ->all();
    }

    /**
     * The non-empty primary-language options keyed by their position in the stored list,
     * which is the position of their translations.
     *
     * @return array<int, string>
     */
    protected function getIndexedPrimaryResponseOptions(): array
    {
        $options = $this->getMeta('response_options');

        if (is_string($options)) {
            $options = [$options];
        }

        return collect(is_array($options) ? array_values($options) : [])
            ->filter(fn (mixed $option) => is_scalar($option) && trim((string) $option) !== '')
            ->map(fn (mixed $option) => (string) $option)
            ->all();
    }

    /**
     * Response options keep their positions so each translation stays matched to its
     * primary-language option: an empty option becomes an empty string instead of
     * being removed. Other keys are normalised as usual.
     */
    protected function normalizeTranslationValue(string $key, mixed $value): mixed
    {
        if ($key !== 'response_options') {
            return $this->normalizeContentTranslationValue($key, $value);
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        $items = collect(Arr::wrap($value))
            ->values()
            ->map(fn (mixed $item) => is_scalar($item) ? trim((string) $item) : '')
            ->all();

        while ($items !== [] && end($items) === '') {
            array_pop($items);
        }

        return $items ?: null;
    }

    /**
     * Options for a choice field: the stored primary-language option keyed to its label
     * in the current language.
     *
     * @return array<string, string>
     */
    public function getResponseOptions(): array
    {
        $primaryOptions = $this->getPrimaryResponseOptions();

        if ($primaryOptions === []) {
            return [];
        }

        return array_combine($primaryOptions, $this->translatedResponseOptions());
    }

    /**
     * A stored answer in the current language. Each value that matches a
     * primary-language option shows that option's label; other values are returned
     * unchanged.
     */
    public function displayResponse(mixed $value): mixed
    {
        $options = $this->getResponseOptions();

        if ($options === []) {
            return $value;
        }

        $label = fn (mixed $item) => is_scalar($item) && array_key_exists((string) $item, $options)
            ? $options[(string) $item]
            : $item;

        return is_array($value) ? array_map($label, $value) : $label($value);
    }

    public function getTranslatedName(): ?string
    {
        return $this->translated('name');
    }

    public function getTranslatedDescription(): ?string
    {
        return $this->translated('description');
    }
}
