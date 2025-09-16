<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Plank\Metable\Metable;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class PaymentFormItem extends Model implements Sortable
{
    use BelongsToScheduledConference ,Cachable, Metable, SortableTrait;

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
        'scheduled_conference_id',
        'order_column',
        'payment_type',
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
     * The "booted" method of the model.
     */
    protected static function booted(): void {}

    public function scopePaymentType($query, $type): Builder
    {
        return $query->where('payment_type', $type);
    }

    public function buildSortQuery()
    {
        return static::query()->where('payment_type', $this->payment_type);
    }

    public static function getOptions(): array
    {
        return [
            static::TYPE_TEXT => 'Single text box',
            static::TYPE_TEXTAREA => 'Extended text box',
            static::TYPE_CHECKBOX => 'Checkboxes (you can choose one or more)',
            static::TYPE_RADIO => 'Radio button (you can only choose one)',
            static::TYPE_SELECT => 'Drop down box',
        ];
    }

    public function getFormField() : Field
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

    public function getInfolistEntry()
    {
        return match($this->type){
            default => $this->textEntry(),
        };
    }

    public function textEntry()
    {
        return TextEntry::make($this->getFieldId())
            ->label($this->getMeta('name'))
            ->getStateUsing(fn($record) => $record->getFormItemResponse($this));
    }

    protected function getFieldId(): string
    {
        return 'form_responses.' . $this->getKey();
    }
    
    protected function getResponseOptions() : array 
    {
        return array_combine($this->getMeta('response_options') ?? [], $this->getMeta('response_options') ?? []);
    }

    protected function fieldText(): TextInput
    {
        return TextInput::make($this->getFieldId())
            ->helperText(new HtmlString($this->getMeta('description')))
            ->required($this->required)
            ->label($this->getMeta('name'));
    }

    protected function fieldTextarea(): Textarea
    {
        return Textarea::make($this->getFieldId())
            ->label($this->getMeta('name'))
            ->helperText(new HtmlString($this->getMeta('description')))
            ->required($this->required);
    }

    protected function fieldCheckbox(): CheckboxList
    {
        return CheckboxList::make($this->getFieldId())
            ->label($this->getMeta('name'))
            ->helperText(new HtmlString($this->getMeta('description')))
            ->required($this->required)
            ->options($this->getResponseOptions());
    }

    protected function fieldRadio(): Radio
    {
        return Radio::make($this->getFieldId())
            ->label($this->getMeta('name'))
            ->helperText(new HtmlString($this->getMeta('description')))
            ->required($this->required)
            ->options($this->getResponseOptions());
    }

    protected function fieldSelect(): Select
    {
        return Select::make($this->getFieldId())
            ->label($this->getMeta('name'))
            ->helperText(new HtmlString($this->getMeta('description')))
            ->native(false)
            ->searchable()
            ->required($this->required)
            ->options($this->getResponseOptions());
    }

    protected function fieldUpload(): SpatieMediaLibraryFileUpload
    {
        return SpatieMediaLibraryFileUpload::make($this->getFieldId())
            ->label($this->getMeta('name'))
            ->helperText(new HtmlString($this->getMeta('description')))
            ->downloadable()
            ->required($this->required)
            ->collection($this->getFieldId());
    }

    public static function buildFormSchema(int $paymentType) : array
    {
        return static::query()
            ->paymentType($paymentType)
            ->lazy()
            ->map(fn(PaymentFormItem $item) => $item->getFormField())
            ->toArray();
    }

    public static function buildInfolistSchema(int $paymentType) : array 
    {
        return static::query()
            ->ordered()
            ->paymentType($paymentType)
            ->lazy()
            ->map(fn(PaymentFormItem $item) =>  $item->getInfolistEntry())
            ->toArray();
    }
}
