<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Facades\Setting;
use App\Managers\PaymentManager;
use App\Models\Payment;
use App\Models\PaymentFeeFormItem;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Livewire\Component;
use Squire\Models\Currency;

class SubmissionPaymentFeeTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function mount() {}

    public function render()
    {
        return view('tables.table');
    }

    public function getTableQuery(): Builder
    {
        return Payment::query()
            ->type(PaymentManager::TYPE_SUBMISSION_FEE)
            ->with(['model.conference', 'user']);
    }

    public function table(Table $table): Table
    {
        return $table
            // ->heading('Submission Payments')
            ->query($this->getTableQuery())
            ->columns([
                IndexColumn::make('No'),
                TextColumn::make('title')
                    ->color('primary')
                    ->url(fn (Payment $record) => $record->model ? SubmissionResource::getUrl('view', ['record' => $record->model]) : null)
                    ->getStateUsing(fn (Payment $record) => $record->model?->getMeta('title') ?? '-')
                    ->wrap(),
                TextColumn::make('user.fullName')
                    ->searchable(
                        query: fn ($query, $search) => $query
                            ->whereHas(
                                'user',
                                fn ($query) => $query->whereMeta('public_name', 'LIKE', "%{$search}%")
                                    ->orWhere('given_name', 'LIKE', "%{$search}%")
                                    ->orWhere('family_name', 'LIKE', "%{$search}%")
                            )
                    )
                    ->toggleable(),
                TextColumn::make('amount')
                    ->getStateUsing(fn (Payment $record) => $record->amount ? money($record->amount, $record->currency, true)->formatWithoutZeroes() : 0)
                    ->toggleable(),
                TextColumn::make('paid_at')
                    ->date()
                    ->toggleable(),
                TextColumn::make('payment_method')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('paid_at')
                    ->label('Paid')
                    ->nullable(),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('payment')
                        ->modalWidth(MaxWidth::Large)
                        ->modalCancelActionLabel(__('general.close'))
                        ->mountUsing(function (Form $form, $record) {
                            $form->fill([
                                ...$record->attributesToArray(),
                                'meta' => $record->getAllMeta()->toArray(),
                            ]);
                        })
                        ->visible(fn (Payment $record) => ! $record->paid_at)
                        ->form(function (Form $form, Payment $record) {
                            return $form
                                ->id('payment')
                                ->model($record)
                                ->schema([
                                    Placeholder::make('type')
                                        ->content($record->getPaymentType()),
                                    Placeholder::make('amount')
                                        ->content($record->getFormattedFee())
                                        ->extraAttributes([
                                            'style' => 'font-size:1rem;',
                                        ]),
                                    DatePicker::make('paid_at'),
                                ]);
                        })
                        ->action(fn (array $data, Payment $record) => $record->update([...$data, 'payment_method' => 'manual'])),
                    Action::make('detail')
                        ->mountUsing(function (Form $form, $record) {
                            $form->fill([
                                ...$record->attributesToArray(),
                                'meta' => $record->getAllMeta()->toArray(),
                            ]);
                        })
                        ->modalSubmitAction(false)
                        ->modalWidth(MaxWidth::Large)
                        ->form(function (Form $form, Payment $record) {
                            return $form
                                ->id('paymentForm')
                                ->disabled()
                                ->model($record)
                                ->schema([
                                    Placeholder::make('title')
                                        ->content($record->getMeta('title')),
                                    Placeholder::make('type')
                                        ->content($record->getPaymentType()),
                                    Placeholder::make('amount')
                                        ->content($record->getFormattedFee())
                                        ->extraAttributes([
                                            'style' => 'font-size:1rem;',
                                        ]),
                                    Placeholder::make('paid_at')
                                        ->visible($record->paid_at ? true : false)
                                        ->content($record->paid_at?->format(Setting::get('format_date').' '.Setting::get('format_time')))
                                        ->extraAttributes([
                                            'style' => 'font-size:1rem;',
                                        ]),
                                    Placeholder::make('description')
                                        ->content($record->getMeta('description'))
                                        ->visible($record->getMeta('description') ?? false),
                                    ...$record->fee?->formItems?->map(fn (PaymentFeeFormItem $item) => $item->getFormField())->toArray(),
                                    Radio::make('payment_method')
                                        ->required()
                                        ->reactive()
                                        ->options(PaymentManager::get()->getPaymentMethodOptions()),
                                ]);
                        }),
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
                            ->required()
                            ->unique(
                                ignorable: fn () => $form->getRecord(),
                                modifyRuleUsing: fn (Unique $rule) => $rule->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId()),
                            ),
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
            ]);
    }
}
