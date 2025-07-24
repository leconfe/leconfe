<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Facades\Setting;
use App\Forms\Components\TinyEditor;
use App\Mail\MailUser;
use App\Managers\PaymentManager;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Models\PaymentFeeFormItem;
use App\Panel\ScheduledConference\Pages\PaymentDetail;
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
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Livewire\Component;
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
            ->queryStringIdentifier('participant_payment_fees')
            ->recordUrl(fn(Payment $record) => PaymentDetail::getUrl(['record' => $record]))

            ->columns([
                IndexColumn::make('No'),
                TextColumn::make('invoice')
                    ->visible(app()->getCurrentScheduledConference()?->isInvoiceEnabled())
                    ->searchable()
                    ->wrap(),
                TextColumn::make('model.full_name')
                    ->label('Name')
                    ->description(fn ($record) => $record->model->email),
                TextColumn::make('fee.name')
                    ->description(fn(Payment $record) => $record->amount ? $record->getFormattedFee() : 0)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Registered at')
                    ->sortable()
                    ->toggleable()
                    ->date(),
                TextColumn::make('paid_at')
                    ->date()
                    ->toggleable()
                    ->toggleable(),
            ])
            ->headerActions([
                Action::make('mail')
                    ->label('Send Email')
                    ->icon('heroicon-o-envelope')
                    ->visible(fn () => $this->getTableQuery()->count())
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
                            ->when($data['participant_fee'], fn (Builder $query, $value) => $query->where('payment_fee_id', $value))
                            ->when($data['payment_status'], fn (Builder $query, $value) => $query->paid($value === 'paid'))
                            ->get()
                            ->each(fn ($payment) => Mail::to($payment->model->email, $payment->model->full_name)->send(new MailUser($data['subject'], $data['message'])));

                        $action->success();

                    })
                    ->successNotificationTitle('Sending Email in Background'),
            ])
            ->filters([
                SelectFilter::make('payment_fee_id')
                    ->label('Payment Fee')
                    ->options(
                        PaymentFee::query()
                            ->type(PaymentManager::TYPE_PARTICIPANT_FEE)
                            ->pluck('name', 'id')
                    ),
                TernaryFilter::make('paid_at')
                    ->label('Paid')
                    ->nullable(),
            ])
            ->actions([
                ActionGroup::make([
                    DeleteAction::make()
                        ->hidden(fn (Payment $record) => $record->paid_at)
                        ->using(function (Payment $record) {
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
