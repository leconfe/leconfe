<?php

namespace App\Models\Concerns;

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
    use HasTranslatableContent;

    /**
     * The primary-language response options.
     *
     * @return array<int, string>
     */
    public function getPrimaryResponseOptions(): array
    {
        $options = $this->getMeta('response_options');

        if (is_string($options)) {
            $options = [$options];
        }

        return collect(is_array($options) ? $options : [])
            ->filter(fn (mixed $option) => is_scalar($option))
            ->map(fn (mixed $option) => (string) $option)
            ->values()
            ->all();
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
        $primaryOptions = $this->getPrimaryResponseOptions();
        $primary = $this->getPrimaryContentLocale();
        $localized = $this->getStoredTranslation('response_options', $locale ?? app()->getLocale()) ?? [];
        $primaryLocalized = $this->getStoredTranslation('response_options', $primary) ?? [];

        return collect($primaryOptions)
            ->map(function (string $option, int $index) use ($localized, $primaryLocalized) {
                foreach ([$localized[$index] ?? null, $primaryLocalized[$index] ?? null] as $label) {
                    if (! static::isBlankContent($label)) {
                        return (string) $label;
                    }
                }

                return $option;
            })
            ->all();
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
