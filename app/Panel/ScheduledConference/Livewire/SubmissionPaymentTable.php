<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Mail\Templates\SubmissionPaymentMail;
use App\Managers\PaymentManager;
use App\Models\Enums\SubmissionStatus;
use App\Models\MailTemplate;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Models\Submission;
use App\Notifications\SubmissionPayment;
use App\Panel\ScheduledConference\Pages\PaymentDetail;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use App\Services\Billing\InvoicePaymentContextResolver;
use App\Support\Multilingual\RecipientLocale;
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

    public static function getSubmissionStatusBadgeStates(Payment $record): array
    {
        $submission = $record->model;

        if (! $submission instanceof Submission) {
            return [];
        }

        return collect([
            $submission->status?->value,
            SubmissionResource::getLatestReviewRoundBadgeState($submission),
        ])
            ->filter()
            ->values()
            ->all();
    }

    public static function getSubmissionStatusBadgeColor(string $state): string
    {
        return SubmissionStatus::tryFrom($state)?->getColor() ?? 'info';
    }

    public static function getValidSubmissionStatusValues(): array
    {
        return collect(SubmissionStatus::cases())
            ->reject(fn (SubmissionStatus $status) => in_array($status, [
                SubmissionStatus::Declined,
                SubmissionStatus::PaymentDeclined,
                SubmissionStatus::Withdrawn,
            ], true))
            ->map(fn (SubmissionStatus $status) => $status->value)
            ->values()
            ->all();
    }

    public function getTableQuery(): Builder
    {
        return Payment::query()
            ->type(PaymentManager::TYPE_SUBMISSION_FEE)
            ->whereHasMorph(
                'model',
                [Submission::class],
                fn (Builder $query) => $query->whereIn('status', static::getValidSubmissionStatusValues()),
            )
            ->with(['model.conference', 'model.latestReviewRound', 'model.translate', 'user', 'scheduledConference']);
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
                    ->state(fn (Payment $record) => ($record->model instanceof Submission ? $record->model->translated('title') : $record->model?->getMeta('title')) ?? '-')
                    ->description(fn (Payment $record) => $record->user->full_name)
                    ->wrap(),
                TextColumn::make('submission_status')
                    ->label('Submission Status')
                    ->badge()
                    ->toggleable()
                    ->state(fn (Payment $record) => static::getSubmissionStatusBadgeStates($record))
                    ->color(fn (string $state): string => static::getSubmissionStatusBadgeColor($state))
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
                SelectFilter::make('submission_status')
                    ->label('Submission Status')
                    ->options(array_combine(
                        static::getValidSubmissionStatusValues(),
                        static::getValidSubmissionStatusValues(),
                    ))
                    ->query(function (Builder $query, array $data): Builder {
                        $status = $data['value'] ?? null;

                        if (blank($status)) {
                            return $query;
                        }

                        return $query->whereHasMorph(
                            'model',
                            [Submission::class],
                            fn (Builder $query) => $query->where('status', $status),
                        );
                    }),
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

                            $submission->user->notify(new SubmissionPayment($record->getKey()));
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
                    ->mountUsing(function (Form $form, Collection $records): void {
                        // A single recipient gets the template in their language, several get the site default language.
                        $recipient = $records->count() === 1
                            ? rescue(fn () => app(InvoicePaymentContextResolver::class)->submission($records->first()->getKey())->user, report: false)
                            : null;
                        $mailTemplate = MailTemplate::contentFor(SubmissionPaymentMail::class, $recipient ? RecipientLocale::for($recipient) : null);
                        $form->fill([
                            'subject' => $mailTemplate['subject'],
                            'message' => $mailTemplate['html_template'],
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
                        $records->each(function ($record) use ($data) {
                            $record->ensureInvoice();
                            $submission = app(InvoicePaymentContextResolver::class)->submission($record->getKey());

                            if (! $submission->user) {
                                return;
                            }

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
