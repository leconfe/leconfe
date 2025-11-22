<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Forms\Components\TinyEditor;
use App\Mail\MailUser;
use App\Mail\Templates\ParticipantPaymentMail;
use App\Managers\PaymentManager;
use App\Models\DefaultMailTemplate;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Notifications\ParticipantPayment;
use App\Panel\ScheduledConference\Pages\PaymentDetail;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
            ->with([
                'model',
                'user',
                'scheduledConference'
            ]);
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
                    ->description(fn($record) => $record->model->email),
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
            ->headerActions([])
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
                        ->hidden(fn(Payment $record) => $record->isPaid())
                        ->using(function (Payment $record) {
                            $record->delete();

                            $record->model->delete();

                            return $record;
                        }),
                ]),
            ])
            ->bulkActions([
                BulkAction::make('send-email')
                    ->mountUsing(function (Form $form): void {
                        $mailTemplate = DefaultMailTemplate::where('mailable', ParticipantPaymentMail::class)->first();
                        $form->fill([
                            'subject' => $mailTemplate ? $mailTemplate->subject : '',
                            'message' => $mailTemplate ? $mailTemplate->html_template : '',
                        ]);
                    })
                    ->form([
                        TextInput::make('subject')
                            ->label(__('general.subject'))
                            ->required(),
                        RichEditor::make('message')
                            ->label(__('general.message'))
                            ->disableToolbarButtons(['attachFiles'])
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data, BulkAction $action) {
                        $records->load(['model.payment' => [
                            'scheduledConference',
                            'fee'
                        ]]);

                        $records->each(function ($record) use ($data) {
                            $participant = $record->model;


                            $mailTemplate = new ParticipantPaymentMail($participant);
                            $mailTemplate->subjectUsing($data['subject']);
                            $mailTemplate->contentUsing($data['message']);
                            Mail::to($participant->email)->send($mailTemplate);
                        });

                        $action->success();
                    })
                    ->successNotificationTitle('Success sending email.')
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
