<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduledConference;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Plank\Metable\Metable;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class RegistrationForm extends Model implements Sortable
{
    use HasFactory, Metable, BelongsToScheduledConference, SortableTrait;

    public const TYPE_TEXT = 1;
    public const TYPE_TEXTAREA = 2;
    public const TYPE_CHECKBOX = 3;
    public const TYPE_RADIO = 4;
    public const TYPE_SELECT = 5;
    public const TYPE_REGISTRATION_TYPE = 6;

    protected $fillable = [
        'label',
        'scheduled_conference_id',
        'type',
        'order_column',
        'is_default',
    ];

    protected $casts = [
        'type' => 'integer',
        'weight' => 'double',
        'is_default',
    ];

    public static function getOptions(): array
    {
        return [
            static::TYPE_TEXT => static::getTypeLabel(static::TYPE_TEXT),
            static::TYPE_TEXTAREA => static::getTypeLabel(static::TYPE_TEXTAREA),
            static::TYPE_SELECT => static::getTypeLabel(static::TYPE_SELECT),
            static::TYPE_CHECKBOX => static::getTypeLabel(static::TYPE_CHECKBOX),
            static::TYPE_RADIO => static::getTypeLabel(static::TYPE_RADIO),
        ];
    }

    public static function getTypeLabel(int $type) : string
    {
        return match ($type) {
           static::TYPE_TEXT => 'Single text box',
            static::TYPE_TEXTAREA => 'Extended text box',
            static::TYPE_SELECT => 'Drop down box',
            static::TYPE_CHECKBOX => 'Checkboxes (can choose one or more)',
            static::TYPE_RADIO => 'Radio button (can only choose one)',
            static::TYPE_REGISTRATION_TYPE => 'Registration type',
            default => __('scheduled_conference.option_unavailable'),
        };
    }


    public function getFormField(): Component
    {
        return match ($this->type) {
            static::TYPE_TEXT => $this->fieldText(),
            static::TYPE_TEXTAREA => $this->fieldTextarea(),
            static::TYPE_CHECKBOX => $this->fieldCheckbox(),
            static::TYPE_RADIO => $this->fieldRadio(),
            static::TYPE_SELECT => $this->fieldSelect(),
            static::TYPE_REGISTRATION_TYPE => $this->fieldRegistrationType(),
        };
    }

    protected function getFieldId(): string
    {
        return 'meta.registration_form.' . $this->getKey();
    }

    protected function fieldText(): TextInput
    {
        return TextInput::make($this->getFieldId())
            ->helperText($this->getMeta('description'))
            ->required($this->getMeta('required'))
            ->label($this->label);
    }

    protected function fieldTextarea(): Textarea
    {
        return Textarea::make($this->getFieldId())
            ->label($this->label)
            ->helperText($this->getMeta('description'))
            ->required($this->getMeta('required'));
    }

    protected function fieldCheckbox(): CheckboxList
    {
        return CheckboxList::make($this->getFieldId())
            ->label($this->label)
            ->helperText($this->getMeta('description'))
            ->required($this->getMeta('required'))
            ->options($this->getMeta('options') ?? []);
    }

    protected function fieldRadio(): Radio
    {
        return Radio::make($this->getFieldId())
            ->label($this->label)
            ->helperText($this->getMeta('description'))
            ->required($this->getMeta('required'))
            ->options($this->getMeta('options') ?? []);
    }

    protected function fieldSelect(): Select
    {
        return Select::make($this->getFieldId())
            ->label($this->label)
            ->helperText($this->getMeta('description'))
            ->required($this->getMeta('required'))
            ->options($this->getMeta('options') ?? []);
    }

    protected function fieldRegistrationType(): Radio
    {
        $registrationTypes = RegistrationType::query()
                    ->with(['meta'])
                    ->get();

        return Radio::make($this->getFieldId())
            ->label($this->label)
            ->required($this->getMeta('required'))
            ->options($registrationTypes->pluck('name', 'id'))
            ->descriptions($registrationTypes->mapWithKeys(fn(RegistrationType $record) => [$record->getKey() => money($record->cost, $record->currency, true)->formatWithoutZeroes()]));
    }

    protected function getAllDefaultMeta(): array
    {
        return [
            'required' => false,
        ];
    }

    public function isDefault(): bool
    {
        return $this->is_default;
    }
}
