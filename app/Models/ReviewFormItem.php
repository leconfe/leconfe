<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use App\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Plank\Metable\Metable;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class ReviewFormItem extends Model implements Sortable
{
    use BelongsToScheduledConference, Cachable, HasFactory, Metable, SortableTrait;

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

    public static function getOptions(): array
    {
        return [
            static::TYPE_TEXT => 'Single text box',
            static::TYPE_TEXTAREA => 'Extended text box',
            static::TYPE_SELECT => 'Drop down box with weight scoring.',
            static::TYPE_CHECKBOX => 'Checkboxes (you can choose one or more)',
            static::TYPE_RADIO => 'Radio button (you can only choose one)',
            static::TYPE_UPLOAD => 'Upload File',
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
            ->helperText(new HtmlString($this->getMeta('description')))
            ->required($this->getMeta('required'))
            ->label($this->label);
    }

    protected function fieldTextarea(): Textarea
    {
        return Textarea::make($this->getFieldId())
            ->label($this->label)
            ->helperText(new HtmlString($this->getMeta('description')))
            ->required($this->getMeta('required'));
    }

    protected function fieldCheckbox(): CheckboxList
    {
        return CheckboxList::make($this->getFieldId())
            ->label($this->label)
            ->helperText(new HtmlString($this->getMeta('description')))
            ->required($this->getMeta('required'))
            ->options($this->getMeta('checkbox_options') ?? []);
    }

    protected function fieldRadio(): Radio
    {
        return Radio::make($this->getFieldId())
            ->label($this->label)
            ->helperText(new HtmlString($this->getMeta('description')))
            ->required($this->getMeta('required'))
            ->options($this->getMeta('radio_options') ?? []);
    }

    protected function fieldSelect(): Select
    {
        return Select::make($this->getFieldId())
            ->label($this->label)
            ->helperText(new HtmlString($this->getMeta('description')))
            ->required($this->getMeta('required'))
            ->options(collect($this->getMeta('select_options') ?? [])->mapWithKeys(fn ($item, $key) => [$item['value'] => $item['label']])->toArray());
    }

    protected function fieldUpload(): SpatieMediaLibraryFileUpload
    {
        return SpatieMediaLibraryFileUpload::make($this->getFieldId())
            ->label($this->label)
            ->helperText(new HtmlString($this->getMeta('description')))
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
            static::TYPE_SELECT => optional(collect($this->getMeta('select_options') ?? [])->firstWhere('value', $value))['label'] ?? '-',
            static::TYPE_CHECKBOX => collect($this->getMeta('checkbox_options') ?? [])->only($value ?? [])->implode(', '),
            static::TYPE_RADIO => collect($this->getMeta('radio_options') ?? [])->get($value, '-'),
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
