<?php

namespace App\Models;

use App\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Models\Concerns\BelongsToScheduledConference;
use App\Models\Concerns\HasTranslatableContent;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Plank\Metable\Metable;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class ReviewFormItem extends Model implements Sortable
{
    use BelongsToScheduledConference, Cachable, HasFactory, Metable, SortableTrait;
    use HasTranslatableContent {
        fillTranslatableFormState as fillContentTranslatableFormState;
        getTranslationSource as getContentTranslationSource;
        normalizeTranslationValue as normalizeContentTranslationValue;
        syncTranslatableState as syncContentTranslatableState;
    }

    public const TYPE_TEXT = 1;

    public const TYPE_TEXTAREA = 2;

    public const TYPE_CHECKBOX = 3;

    public const TYPE_RADIO = 4;

    public const TYPE_SELECT = 5;

    public const TYPE_UPLOAD = 6;

    protected $table = 'submission_review_form_items';

    protected $fillable = [
        'label',
        'scheduled_conference_id',
        'type',
        'weight',
        'order_column',
    ];

    protected $casts = [
        'type' => 'integer',
        'weight' => 'double',
    ];

    /**
     * @var array<int, string>
     */
    protected array $translatableColumns = ['label'];

    /**
     * @var array<int, string>
     */
    protected array $translatableMeta = ['description', 'checkbox_options', 'radio_options', 'select_option_labels'];

    /**
     * @var array<int, string>
     */
    protected array $translatableListMeta = ['checkbox_options', 'radio_options', 'select_option_labels'];

    public static function getOptions(): array
    {
        return [
            static::TYPE_TEXT => __('general.item_type_text'),
            static::TYPE_TEXTAREA => __('general.item_type_textarea'),
            static::TYPE_SELECT => __('general.item_type_select_scored'),
            static::TYPE_CHECKBOX => __('general.item_type_checkbox'),
            static::TYPE_RADIO => __('general.item_type_radio'),
            static::TYPE_UPLOAD => __('general.item_type_upload'),
        ];
    }

    public function isUploadType(): bool
    {
        return $this->type === static::TYPE_UPLOAD;
    }

    public function isEnableScoring(): bool
    {
        return $this->type === static::TYPE_SELECT && filled($this->weight);
    }

    public function getFieldId(): string
    {
        return 'meta.review_responses.'.$this->getKey();
    }

    public function getFormField(): Component
    {
        return match ($this->type) {
            static::TYPE_TEXT => $this->fieldText(),
            static::TYPE_TEXTAREA => $this->fieldTextarea(),
            static::TYPE_CHECKBOX => $this->fieldCheckbox(),
            static::TYPE_RADIO => $this->fieldRadio(),
            static::TYPE_SELECT => $this->fieldSelect(),
            static::TYPE_UPLOAD => $this->fieldUpload(),
        };
    }

    protected function fieldText(): TextInput
    {
        return TextInput::make($this->getFieldId())
            ->helperText($this->getDescriptionHtml())
            ->required($this->getMeta('required'))
            ->label($this->translated('label'));
    }

    protected function fieldTextarea(): Textarea
    {
        return Textarea::make($this->getFieldId())
            ->label($this->translated('label'))
            ->helperText($this->getDescriptionHtml())
            ->required($this->getMeta('required'));
    }

    protected function fieldCheckbox(): CheckboxList
    {
        return CheckboxList::make($this->getFieldId())
            ->label($this->translated('label'))
            ->helperText($this->getDescriptionHtml())
            ->required($this->getMeta('required'))
            ->options($this->translatedOptions('checkbox_options'));
    }

    protected function fieldRadio(): Radio
    {
        return Radio::make($this->getFieldId())
            ->label($this->translated('label'))
            ->helperText($this->getDescriptionHtml())
            ->required($this->getMeta('required'))
            ->options($this->translatedOptions('radio_options'));
    }

    protected function fieldSelect(): Select
    {
        return Select::make($this->getFieldId())
            ->label($this->translated('label'))
            ->helperText($this->getDescriptionHtml())
            ->required($this->getMeta('required'))
            ->options($this->getSelectOptions());
    }

    protected function fieldUpload(): SpatieMediaLibraryFileUpload
    {
        return SpatieMediaLibraryFileUpload::make($this->getFieldId())
            ->label($this->translated('label'))
            ->helperText($this->getDescriptionHtml())
            ->required($this->getMeta('required'))
            ->disk('private-files')
            ->visibility('private')
            ->multiple(false)
            ->downloadable()
            ->saveRelationshipsUsing(static function (SpatieMediaLibraryFileUpload $component) {
                if (! $component->getRecord() instanceof Review) {
                    return;
                }

                $component->saveUploadedFiles();
            })
            ->collection($this->getFieldId());
    }

    protected function getDescriptionHtml(): HtmlString
    {
        return new HtmlString($this->translated('description'));
    }

    /**
     * Select options keyed by their stored value, labelled in the current language.
     *
     * @return array<string|int, string>
     */
    public function getSelectOptions(): array
    {
        $labels = $this->translatedOptions('select_option_labels');

        return collect($this->getMeta('select_options') ?? [])
            ->values()
            ->filter(fn (mixed $item) => is_array($item) && array_key_exists('value', $item))
            ->mapWithKeys(fn (array $item, int $index) => [$item['value'] => $labels[$index] ?? $item['label'] ?? ''])
            ->all();
    }

    /**
     * The options of a list key in the given language, or the current language, keyed by
     * their position in the primary-language list, which is what answers store. An
     * option without a translation falls back to the primary-language option.
     *
     * @return array<int, string>
     */
    public function translatedOptions(string $key, ?string $locale = null): array
    {
        $localized = $this->getStoredTranslation($key, $locale ?? app()->getLocale()) ?? [];
        $primaryLocalized = $this->getStoredTranslation($key, $this->getPrimaryContentLocale()) ?? [];

        return collect($this->getPrimaryOptions($key))
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
     * @return array<int, string>
     */
    protected function getPrimaryOptions(string $key): array
    {
        $options = $this->getTranslationSource($key);

        return collect(is_array($options) ? array_values($options) : [])
            ->map(fn (mixed $option) => is_scalar($option) ? (string) $option : '')
            ->all();
    }

    public function getTranslationSource(string $key): mixed
    {
        if ($key === 'select_option_labels') {
            return collect($this->getMeta('select_options') ?? [])
                ->map(fn (mixed $item) => is_array($item) ? ($item['label'] ?? '') : '')
                ->values()
                ->all();
        }

        return $this->getContentTranslationSource($key);
    }

    protected function normalizeTranslationValue(string $key, mixed $value): mixed
    {
        if (! $this->isTranslatableList($key)) {
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

    public function fillTranslatableFormState(array $state, array $formKeys): array
    {
        $state = $this->fillContentTranslatableFormState($state, $formKeys);

        if (count($this->getContentLocalesForForm()) < 2 || ! is_array(data_get($state, 'meta.select_options'))) {
            return $state;
        }

        $translations = $this->getTranslationsForForm('select_option_labels');

        $state['meta']['select_options'] = collect($state['meta']['select_options'])
            ->values()
            ->map(fn (mixed $item, int $index) => [
                ...Arr::wrap($item),
                'label' => collect($translations)->map(fn (array $labels) => $labels[$index] ?? null)->all(),
            ])
            ->all();

        return $state;
    }

    public function syncTranslatableState(array $data): array
    {
        $selectOptions = data_get($data, 'meta.select_options');

        if (is_array($selectOptions)) {
            $selectOptions = array_values(array_filter($selectOptions, 'is_array'));
            $labels = array_column($selectOptions, 'label');

            if (collect($labels)->contains(fn (mixed $label) => is_array($label))) {
                $primary = $this->getPrimaryContentLocale();
                $locales = collect($labels)->flatMap(fn (mixed $label) => array_keys(Arr::wrap($label)))->unique();

                $labels = $locales
                    ->mapWithKeys(fn (string $locale) => [$locale => collect($selectOptions)->map(fn (array $item) => $item['label'][$locale] ?? null)->all()])
                    ->all();

                $selectOptions = collect($selectOptions)
                    ->map(fn (array $item) => [...$item, 'label' => $item['label'][$primary] ?? null])
                    ->all();
            }

            data_set($data, 'meta.select_options', $selectOptions);
            data_set($data, 'meta.select_option_labels', $labels);
        }

        $data = $this->syncContentTranslatableState($data);

        Arr::forget($data, 'meta.select_option_labels');

        return $data;
    }

    protected function getAllDefaultMeta(): array
    {
        return [
            'required' => false,
        ];
    }

    public function getContentFromValue($value, ?Review $review = null): mixed
    {
        return match ($this->type) {
            static::TYPE_TEXT, static::TYPE_TEXTAREA => $value ?? '-',
            static::TYPE_SELECT => $this->getSelectOptions()[$value] ?? '-',
            static::TYPE_CHECKBOX => collect($this->translatedOptions('checkbox_options'))->only($value ?? [])->implode(', '),
            static::TYPE_RADIO => collect($this->translatedOptions('radio_options'))->get($value, '-'),
            static::TYPE_UPLOAD => $review?->getMedia($this->getFieldId())
                ->map(fn (Media $media) => $media->original_file_name)
                ->implode(', ') ?: '-',
        };
    }

    public static function filterOutUploadResponses(?array $responses): array
    {
        if (! is_array($responses)) {
            return [];
        }

        $uploadFieldIds = static::query()
            ->where('type', static::TYPE_UPLOAD)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return collect($responses)
            ->reject(fn ($value, $key) => in_array((string) $key, $uploadFieldIds, true))
            ->toArray();
    }
}
