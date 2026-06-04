<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Mail\Templates\SubmissionPaymentMail;
use App\Managers\PaymentManager;
use App\Models\DefaultMailTemplate;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Notifications\SubmissionPayment;
use App\Panel\ScheduledConference\Pages\PaymentDetail;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\RichEditor;
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
use Livewire\Component;

class SubmissionPaymentTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function mount() {}

    public function render()
    {
        return view('tables.table');
    }

    public static function canSendInvoiceFor(Payment $record): bool
    {
        return ! $record->isPaid()
            && (bool) $record->scheduledConference?->isInvoiceEnabled();
    }

    public function getTableQuery(): Builder
    {
        return Payment::query()
            ->type(PaymentManager::TYPE_SUBMISSION_FEE)
            ->with(['model.conference', 'user', 'scheduledConference']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->queryStringIdentifier('submission_payment')
            ->recordUrl(fn (Payment $record) => PaymentDetail::getUrl(['record' => $record]))
            ->columns([
                IndexColumn::make('No'),
                TextColumn::make('invoice')
                    ->visible(app()->getCurrentScheduledConference()?->isInvoiceEnabled())
                    ->searchable()
                    ->wrap(),
                TextColumn::make('invoice_email_status')
                    ->label(__('general.invoice_email'))
                    ->visible(app()->getCurrentScheduledConference()?->isInvoiceEnabled())
                    ->badge()
                    ->state(fn (Payment $record) => $record->hasInvoiceBeenSent() ? __('general.sent') : __('general.not_sent'))
                    ->color(fn (Payment $record) => $record->hasInvoiceBeenSent() ? 'success' : 'gray'),
                TextColumn::make('title')
                    ->label('Submission Title')
                    ->state(fn (Payment $record) => $record->model?->getMeta('title') ?? '-')
                    ->description(fn (Payment $record) => $record->user->full_name)
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Submission Status')
                    ->badge()
                    ->toggleable()
                    ->state(fn (Payment $record) => $record->model?->status?->value)
                    ->wrap(),
                TextColumn::make('fee.name')
                    ->description(fn (Payment $record) => $record->amount ? $record->getFormattedFee() : 0)
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
            ->filters([
                SelectFilter::make('payment_fee_id')
                    ->label('Payment Fee')
                    ->options(fn () => PaymentFee::query()
                        ->type(PaymentManager::TYPE_SUBMISSION_FEE)
                        ->pluck('name', 'id')),
                TernaryFilter::make('paid_at')
                    ->label('Paid')
                    ->nullable(),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('send-invoice')
                        ->label(__('general.send_invoice'))
                        ->icon('heroicon-o-envelope')
                        ->color('gray')
                        ->visible(fn (Payment $record) => static::canSendInvoiceFor($record))
                        ->requiresConfirmation()
                        ->action(function (Action $action, Payment $record) {
                            $record->ensureInvoice();

                            $submission = $record->model;
                            if (! $submission || ! $submission->user) {
                                $action->failureNotificationTitle(__('general.failed_send_notification'));
                                $action->failure();

                                return;
                            }

                            $submission->setRelation('payment', $record->refresh());
                            $submission->user->notify(new SubmissionPayment($submission));
                            $record->markInvoiceAsSent();
                            $action->successNotificationTitle(__('general.invoice_sent_successfully'));
                            $action->success();
                        }),
                    DeleteAction::make()
                        ->hidden(fn (Payment $record) => $record->isPaid()),
                ]),
            ])
            ->bulkActions([
                BulkAction::make('send-email')
                    ->mountUsing(function (Form $form): void {
                        $mailTemplate = DefaultMailTemplate::where('mailable', SubmissionPaymentMail::class)->first();
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
                        $records->load([
                            'model.user',
                            'scheduledConference.conference',
                        ]);

                        $records->each(function ($record) use ($data) {
                            $submission = $record->model;

                            if (! $submission || ! $submission->user) {
                                return;
                            }

                            $record->ensureInvoice();
                            $submission->setRelation(
                                'payment',
                                $record->refresh()->loadMissing('scheduledConference.conference')
                            );

                            $mailTemplate = new SubmissionPaymentMail($submission);

                            $mailTemplate->subjectUsing($data['subject']);
                            $mailTemplate->contentUsing($data['message']);
                            Mail::to($submission->user)->send($mailTemplate);
                            $record->markInvoiceAsSent();
                        });

                        $action->success();
                    })
                    ->successNotificationTitle('Success sending email.'),
            ]);
    }
}
