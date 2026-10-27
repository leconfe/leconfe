<?php

namespace App\Models;

use App\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Models\Concerns\HasTranslatableFormItem;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;
use Plank\Metable\Metable;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class PaymentFeeFormItem extends Model implements Sortable
{
    use Cachable, HasTranslatableFormItem, Metable, SortableTrait;

    public const TYPE_TEXT = 1;

    public const TYPE_TEXTAREA = 2;

    public const TYPE_CHECKBOX = 3;

    public const TYPE_RADIO = 4;

    public const TYPE_SELECT = 5;

    public const TYPE_UPLOAD = 6;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_column',
        'type',
        'required',
        'included',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @var array<int, string>
     */
    protected array $translatableMeta = ['name', 'description', 'response_options'];

    /**
     * @var array<int, string>
     */
    protected array $translatableListMeta = ['response_options'];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void {}

    public function paymentFee(): BelongsTo
    {
        return $this->belongsTo(PaymentFee::class);
    }

    public static function getOptions(): array
    {
        return [
            static::TYPE_TEXT => __('general.item_type_text'),
            static::TYPE_TEXTAREA => __('general.item_type_textarea'),
            static::TYPE_CHECKBOX => __('general.item_type_checkbox'),
            static::TYPE_RADIO => __('general.item_type_radio'),
            static::TYPE_SELECT => __('general.item_type_select'),
            static::TYPE_UPLOAD => __('general.item_type_upload'),
        ];
    }

    public function getFormField()
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

    protected function getFieldId(): string
    {
        return 'meta.form_responses.'.$this->getKey();
    }

    protected function fieldText(): TextInput
    {
        return TextInput::make($this->getFieldId())
            ->helperText(new HtmlString($this->translated('description')))
            ->required($this->required)
            ->label($this->translated('name'));
    }

    protected function fieldTextarea(): Textarea
    {
        return Textarea::make($this->getFieldId())
            ->label($this->translated('name'))
            ->helperText(new HtmlString($this->translated('description')))
            ->required($this->required);
    }

    protected function fieldCheckbox(): CheckboxList
    {
        return CheckboxList::make($this->getFieldId())
            ->label($this->translated('name'))
            ->helperText(new HtmlString($this->translated('description')))
            ->required($this->required)
            ->options($this->getResponseOptions());
    }

    protected function fieldRadio(): Radio
    {
        return Radio::make($this->getFieldId())
            ->label($this->translated('name'))
            ->helperText(new HtmlString($this->translated('description')))
            ->required($this->required)
            ->options($this->getResponseOptions());
    }

    protected function fieldSelect(): Select
    {
        return Select::make($this->getFieldId())
            ->label($this->translated('name'))
            ->helperText(new HtmlString($this->translated('description')))
            ->native(false)
            ->searchable()
            ->required($this->required)
            ->options($this->getResponseOptions());
    }

    protected function fieldUpload(): SpatieMediaLibraryFileUpload
    {
        return SpatieMediaLibraryFileUpload::make($this->getFieldId())
            ->label($this->translated('name'))
            ->helperText(new HtmlString($this->translated('description')))
            ->downloadable()
            ->required($this->required)
            ->collection($this->getMeta('name'));
    }
}
