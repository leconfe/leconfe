<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Facades\Setting;
use App\Frontend\ScheduledConference\Pages\ParticipantForm;
use App\Infolists\Components\LivewireEntry;
use App\Managers\PaymentManager;
use App\Models\PaymentFee;
use App\Tables\Columns\IndexColumn;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Livewire\Component;
use Squire\Models\Currency;

class PaymentFeeTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public int $paymentType;

    public function mount(int $paymentType) {}

    public function render()
    {
        return view('tables.table');
    }

    public function getTableQuery(): Builder
    {
        return PaymentFee::query()
            ->with(['formItems', 'payments'])
            ->type($this->paymentType)
            ->ordered();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->queryStringIdentifier('payment_fees_' . $this->paymentType)
            ->columns([
                IndexColumn::make('No'),
                TextColumn::make('name')
                    ->grow(false)
                    ->description(function (PaymentFee $record) {
                        $description = '';
                        $description .= $record->opened_at?->format(Setting::get('format_date'));

                        if ($record->opened_at && $record->closed_at) {
                            $description .= ' - ' . $record->closed_at->format(Setting::get('format_date'));
                        }

                        return $description;
                    }),
                TextColumn::make('amount')
                    ->getStateUsing(fn(Model $record) => money($record->amount, $record->currency, true)->formatWithoutZeroes()),
                ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->form(fn(Form $form) => $this->form($form))
                    ->using(function ($data) {
                        $record = new PaymentFee;
                        $record->fill($data);
                        $record->type = $this->paymentType;
                        $record->save();

                        if (array_key_exists('meta', $data) && is_array($data['meta'])) {
                            $record->setManyMeta($data['meta']);
                        }

                        return $record;
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->mutateRecordDataUsing(function (PaymentFee $record, array $data): array {
                            $data['meta'] = $record->getAllMeta();
                            return $data;
                        })
                        ->form(fn(Form $form) => $this->form($form))
                        ->using(function (PaymentFee $record, array $data) {
                            $record->update($data);

                            if (array_key_exists('meta', $data) && is_array($data['meta'])) {
                                $record->setManyMeta($data['meta']);
                            }

                            return $record;
                        }),
                    Action::make('items')
                        ->label('Form Items')
                        ->modalWidth(MaxWidth::TwoExtraLarge)
                        ->icon('heroicon-m-list-bullet')
                        ->modalCancelAction(false)
                        ->modalSubmitAction(false)
                        ->modalHeading(false)
                        ->infolist(fn($record) => [
                            LivewireEntry::make('form-items')
                                ->livewire(PaymentFeeFormItemTable::class, ['record' => $record]),
                        ]),
                    DeleteAction::make()
                        ->hidden(fn(PaymentFee $record) => $record->payments->count()),
                ]),
            ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('general.name'))
                            ->required(),
                        TextInput::make('limit')
                            ->label('Limit')
                            ->placeholder('Enter 0 for no limit')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ]),
                Textarea::make('meta.description')
                    ->label(__('general.description'))
                    ->autosize(),
                Grid::make()
                    ->schema([
                        Select::make('currency')
                            ->label(__('general.currency'))
                            ->formatStateUsing(fn($state) => ($state !== null) ? ($state !== 'free' ? $state : null) : null)
                            ->options(fn() => Currency::query()->orderBy('code_numeric', 'asc')->get()
                                ->mapWithKeys(function (?Currency $value, int $key) {
                                    $currencyCode = Str::upper($value->id);
                                    $currencyName = $value->name;

                                    return [$value->id => "($currencyCode) $currencyName"];
                                }))
                            ->searchable()
                            ->required(),
                        TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                    ]),
                KeyValue::make('meta.additional_items')
                    ->keyLabel("Name")
                    ->reorderable()
                    ->rule(fn(): Closure => function (string $attribute, $items, Closure $fail) {
                        // Validate key value items, the value must be a float
                        foreach ($items as $key => $value) {
                            if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                                $fail("Amount of $key must be a number.");
                            }
                        }
                    })
                    ->valueLabel("Amount")
                    ->addActionLabel("Add Item"),
                Grid::make(2)
                    ->schema([
                        DatePicker::make('opened_at')
                            ->label(__('general.opened_at'))
                            ->placeholder(__('general.select_type_opened_date'))
                            ->prefixIcon('heroicon-m-calendar-days')
                            ->before('closed_at'),
                        DatePicker::make('closed_at')
                            ->label(__('general.closed_at'))
                            ->placeholder(__('general.select_type_closed_date'))
                            ->prefixIcon('heroicon-m-calendar-days')
                            ->requiredWith('opened_at')
                            ->after('opened_at'),
                    ]),
                Checkbox::make('is_active'),
            ]);
    }
}
