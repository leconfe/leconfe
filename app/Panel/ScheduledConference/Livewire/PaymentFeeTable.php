<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Facades\Setting;
use App\Filament\Forms\Components\MultilanguageComponent;
use App\Models\PaymentFee;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Livewire;
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
            ->with(['formItems', 'payments', 'translate'])
            ->type($this->paymentType)
            ->ordered();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->queryStringIdentifier('payment_fees_'.$this->paymentType)
            ->columns([
                IndexColumn::make('No'),
                TextColumn::make('name')
                    ->formatStateUsing(fn (PaymentFee $record) => $record->translated('name'))
                    ->grow(false)
                    ->description(function (PaymentFee $record) {
                        $description = '';
                        $description .= $record->opened_at?->format(Setting::get('format_date'));

                        if ($record->opened_at && $record->closed_at) {
                            $description .= ' - '.$record->closed_at->format(Setting::get('format_date'));
                        }

                        return $description;
                    }),
                TextColumn::make('amount')
                    ->getStateUsing(fn (Model $record) => money($record->amount, $record->currency, true)->formatWithoutZeroes()),
                ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->form(fn (Form $form) => $this->form($form))
                    ->using(function ($data) {
                        $record = new PaymentFee;
                        $record->type = $this->paymentType;

                        return $this->saveRecord($record, $data);
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->mutateRecordDataUsing(function (PaymentFee $record, array $data): array {
                            $data['meta'] = $record->getAllMeta()->all();
                            $data = $record->fillTranslatableFormState($data, ['name', 'meta.description']);

                            return $record->fillAdditionalItemFormState($data);
                        })
                        ->form(fn (Form $form) => $this->form($form))
                        ->using(fn (PaymentFee $record, array $data) => $this->saveRecord($record, $data)),
                    Action::make('items')
                        ->label('Form Items')
                        ->modalWidth(MaxWidth::TwoExtraLarge)
                        ->icon('heroicon-m-list-bullet')
                        ->modalCancelAction(false)
                        ->modalSubmitAction(false)
                        ->modalHeading(false)
                        ->infolist(fn ($record) => [
                            Livewire::make(PaymentFeeFormItemTable::class, ['record' => $record]),
                        ]),
                    DeleteAction::make()
                        ->hidden(fn (PaymentFee $record) => $record->payments->count()),
                ]),
            ]);
    }

    protected function saveRecord(PaymentFee $record, array $data): PaymentFee
    {
        $data = $record->syncTranslatableState($data);
        $data = $record->syncAdditionalItemState($data);

        $record->fill($data);
        $record->save();

        if (array_key_exists('meta', $data) && is_array($data['meta'])) {
            $record->setManyMeta($data['meta']);
        }

        $record->persistTranslatableState();
        $record->persistAdditionalItemTranslations();

        return $record;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->schema([
                        MultilanguageComponent::make([
                            TextInput::make('name')
                                ->label(__('general.name'))
                                ->required(),
                        ]),
                        TextInput::make('limit')
                            ->label('Limit')
                            ->placeholder('Enter 0 for no limit')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ]),
                MultilanguageComponent::make([
                    Textarea::make('meta.description')
                        ->label(__('general.description'))
                        ->autosize(),
                ]),
                Grid::make()
                    ->schema([
                        Select::make('currency')
                            ->label(__('general.currency'))
                            ->formatStateUsing(fn ($state) => ($state !== null) ? ($state !== 'free' ? $state : null) : null)
                            ->options(fn () => Currency::query()->orderBy('code_numeric', 'asc')->get()
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
                Repeater::make('meta.additional_items')
                    ->label('Add-on Items')
                    ->schema([
                        Hidden::make('original_key'),
                        MultilanguageComponent::make([
                            TextInput::make('name')
                                ->label('Item Name')
                                ->required(),
                            Textarea::make('description')
                                ->label('Description')
                                ->autosize(),
                        ]),
                        TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->collapsible()
                    ->default([]),
            ]);
    }
}
