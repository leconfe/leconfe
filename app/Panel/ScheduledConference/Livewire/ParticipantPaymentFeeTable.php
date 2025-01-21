<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Facades\Setting;
use App\Forms\Components\TinyEditor;
use App\Mail\MailUser;
use App\Managers\PaymentManager;
use App\Models\Enums\PaymentType;
use App\Models\Payment;
use Filament\Tables;
use Livewire\Component;
use Filament\Forms\Form;
use App\Models\PaymentFee;
use App\Models\PaymentFeeFormItem;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Facades\Filament;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Panel\Conference\Resources\Conferences\AuthorRoleResource;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use App\Tables\Columns\IndexColumn;
use Awcodes\Shout\Components\Shout;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Mail;
use Squire\Models\Currency;

class ParticipantPaymentFeeTable extends Component implements HasForms, HasTable
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
            ->type(PaymentManager::TYPE_PARTICIPANT_FEE)
            ->with(['model.conference', 'user']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                IndexColumn::make('No'),
                TextColumn::make('model.full_name')
                    ->label('Name')
                    ->description(fn($record) => $record->model->email),
                TextColumn::make('fee.name')
                    ->label("Participant Fee"),
                TextColumn::make('amount')
                    ->getStateUsing(fn(Payment $record) => $record->amount ? money($record->amount, $record->currency, true)->formatWithoutZeroes() : 0),
                TextColumn::make('paid_at')
                    ->date(),
            ])
            ->headerActions([
                Action::make('mail')
                    ->label("Send Email")
                    ->icon('heroicon-o-envelope')
                    ->form(function (Form $form) {
                        return $form->schema([
                            TextInput::make('subject')
                                ->label(__('general.subject'))
                                ->required(),
                            Select::make('participant_fee')
                                ->label('Participant Fee')
                                ->placeholder('All')
                                ->options(
                                    PaymentFee::query()
                                        ->type(PaymentManager::TYPE_PARTICIPANT_FEE)
                                        ->pluck('name', 'id')
                                ),
                            Select::make('payment_status')
                                ->label('Payment Status')
                                ->placeholder('All')
                                ->options([
                                    'paid' => 'Paid',
                                    'unpaid' => 'Unpaid',
                                ]),
                            TinyEditor::make('message')
                                ->label(__('general.message'))
                                ->minHeight(500)
                                ->required(),
                        ]);
                    })
                    ->action(function (array $data, $action) {
                        Payment::query()
                            ->with(['model'])
                            ->type(PaymentManager::TYPE_PARTICIPANT_FEE)
                            ->when($data['participant_fee'], fn(Builder $query, $value) => $query->where('payment_fee_id', $value))
                            ->when($data['payment_status'], fn(Builder $query, $value) => $query->paid($value === 'paid'))
                            ->get()
                            ->each(fn($payment) => Mail::to($payment->model->email, $payment->model->full_name)->send(new MailUser($data['subject'], $data['message'])));
                        
                        $action->success();
                        
                    })
                    ->successNotificationTitle('Sending Email in Background'),
            ])
            ->filters([
                SelectFilter::make('payment_fee_id')
                    ->label("Payment Fee")
                    ->options(
                        PaymentFee::query()
                        ->type(PaymentManager::TYPE_PARTICIPANT_FEE)
                        ->pluck('name', 'id')
                    ),
                SelectFilter::make('paid_at')
                    ->options([
                        'paid' => 'Paid',
                        'unpaid' => 'Unpaid',
                    ])
                    ->modifyQueryUsing(fn($query, $data) => $query->when($data['value'], fn($query, $value) => $query->paid($value === 'paid'))),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('payment')
                        ->icon('heroicon-o-banknotes')
                        ->modalWidth(MaxWidth::Large)
                        ->modalCancelActionLabel(__('general.close'))
                        ->mountUsing(function (Form $form, $record) {
                            $form->fill([
                                ...$record->attributesToArray(),
                                'meta' => $record->getAllMeta()->toArray(),
                            ]);
                        })
                        ->visible(fn(Payment $record) => !$record->paid_at)
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
                        ->action(fn(array $data, Payment $record) => $record->update([...$data, 'payment_method' => 'manual'])),
                    Action::make('detail')
                        ->icon('heroicon-o-eye')
                        ->mountUsing(function (Form $form, $record) {
                            $form->fill([
                                ...$record->attributesToArray(),
                                'meta' => $record->getAllMeta()->toArray(),
                            ]);
                        })
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
                                        ->content($record->paid_at?->format(Setting::get('format_date') . ' ' . Setting::get('format_time')))
                                        ->extraAttributes([
                                            'style' => 'font-size:1rem;',
                                        ]),
                                    Placeholder::make('description')
                                        ->content($record->getMeta('description'))
                                        ->visible($record->getMeta('description') ?? false),
                                    ...$record->fee?->formItems?->map(fn(PaymentFeeFormItem $item) => $item->getFormField())->toArray(),
                                    Radio::make('payment_method')
                                        ->required()
                                        ->reactive()
                                        ->options(PaymentManager::get()->getPaymentMethodOptions())
                                ]);
                        }),
                    DeleteAction::make()
                        ->hidden(fn(Payment $record) => $record->paid_at)
                        ->using(function(Payment $record){
                            $record->delete();

                            $record->model->delete();

                            return $record;
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
                                ignorable: fn() => $form->getRecord(),
                                modifyRuleUsing: fn(Unique $rule) => $rule->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId()),
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
