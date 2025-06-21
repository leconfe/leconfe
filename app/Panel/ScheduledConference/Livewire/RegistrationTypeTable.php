<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Facades\Setting;
use App\Frontend\ScheduledConference\Pages\ParticipantForm;
use App\Infolists\Components\LivewireEntry;
use App\Managers\PaymentManager;
use App\Models\PaymentFee;
use App\Models\RegistrationType;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
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

class RegistrationTypeTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function mount() {}

    public function render()
    {
        return view('tables.table');
    }

    public function getTableQuery(): Builder
    {
        return RegistrationType::query()
            ->ordered();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->reorderable('order_column')
            ->columns([
                IndexColumn::make('No'),
                TextColumn::make('name')
                    ->grow(false),
                TextColumn::make('cost')
                    ->getStateUsing(fn (RegistrationType $record) => money($record->cost, $record->currency, true)->formatWithoutZeroes())
                    ->description(fn(RegistrationType $record) => Currency::find($record->currency)?->name),
                TextColumn::make('from')
                    ->date(),
                TextColumn::make('to')
                    ->date(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->form(fn (Form $form) => $this->form($form))
                    ->label('New Type')
                    ->modalWidth(MaxWidth::TwoExtraLarge)
                    ->using(function ($data) {
                        $record = new RegistrationType;
                        $record->fill($data);
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
                        ->mutateRecordDataUsing(function (RegistrationType $record, array $data): array {
                            $data['meta'] = $record->getAllMeta();

                            return $data;
                        })
                        ->form(fn (Form $form) => $this->form($form))
                        ->using(function (RegistrationType $record, array $data) {
                            $record->update($data);

                            if (array_key_exists('meta', $data) && is_array($data['meta'])) {
                                $record->setManyMeta($data['meta']);
                            }

                            return $record;
                        }),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label(__('general.name'))
                    ->required(),
                TextInput::make('limit')
                    ->label('Limit quantity of this registration type')
                    ->numeric(),
                Fieldset::make('Pricing')
                    ->columns(2)
                    ->schema([
                        Select::make('currency')
                            ->required()
                            ->label(__('general.currency'))
                            ->options(fn () => Currency::query()->orderBy('code_numeric', 'asc')->get()
                                ->mapWithKeys(fn (?Currency $currency, int $key) => [$currency->id => $currency->name . " (" . $currency->symbol_native . ")" ]))
                            ->searchable()
                            ->required(),
                        TextInput::make('cost')
                            ->label('Cost')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                    ]),
                
                Fieldset::make('Availability')
                    ->schema([
                        DatePicker::make('from')
                            ->prefixIcon('heroicon-m-calendar-days')
                            ->requiredWith('to')
                            ->before(function(Get $get){
                                return $get('to') ? 'to' : false;
                            }),
                        DatePicker::make('to')
                            ->prefixIcon('heroicon-m-calendar-days')
                            ->after('from'),
                    ]),
            ]);
    }
}
