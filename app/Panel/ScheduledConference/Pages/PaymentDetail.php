<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Facades\Setting;
use App\Mail\Templates\UserPayPaymentMail;
use App\Managers\PaymentManager;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Models\PaymentFormItem;
use App\Notifications\ParticipantPayment;
use App\Notifications\SubmissionPayment;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Mail;

class PaymentDetail extends Page
{
    protected static string $view = 'panel.scheduledConference.pages.payment-detail';

    public Payment $record;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 99;

    public function mount($record = null)
    {
        if (! $record) {
            $record = auth()->user()->participant?->payment;
        }
        
        abort_unless($record && auth()->user()->can('view', $record), 403);
        $this->record = $record;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->isRegisteredAsParticipant();
    }

    public static function getNavigationLabel(): string
    {
        return 'Participant Payment';
    }

    public function getBreadcrumbs(): array
    {
        return [
           Payments::canAccess() ? Payments::getUrl() : 0 => 'Payments',
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return match ($this->record->type) {
            PaymentManager::TYPE_PARTICIPANT_FEE => 'Participant Payment',
            PaymentManager::TYPE_SUBMISSION_FEE => 'Submission Payment',
        };
    }

    protected function getHeaderActions(): array
    {
        $paymentActions = collect(PaymentManager::get()->getPaymentMethodActions())
            ->map(
                fn (Action $action) => $action
                    ->record($this->record)
                    ->model(Payment::class)
                    ->visible(fn (Payment $record) => ! $record->isPaid())
            );

        return [
            ActionGroup::make($paymentActions->toArray())
                ->button()
                ->label('Payment'),
            ActionGroup::make([
                Action::make('edit_payment')
                    ->label('Edit Payment')
                    ->visible(function (Payment $record) {
                        if($record->type == PaymentManager::TYPE_SUBMISSION_FEE && !app()->getCurrentScheduledConference()->isSubmissionPaymentEnabled()){
                            return false;
                        }
                        if($record->type == PaymentManager::TYPE_PARTICIPANT_FEE && !app()->getCurrentScheduledConference()->isParticipantPaymentEnabled()){
                            return false;
                        }

                        return auth()->user()->can('update', $record) && ! $record->isPaid();
                    })
                    ->color('gray')
                    ->record($this->record)
                    ->fillForm([
                        'invoice' => $this->record->invoice,
                        'payment_fee_id' => $this->record->payment_fee_id,
                    ])
                    ->form([
                        TextInput::make('invoice')
                            ->visible(fn () => app()->getCurrentScheduledConference()?->isInvoiceEnabled())
                            ->rule(fn ($record): Closure => function (string $attribute, $value, Closure $fail) use ($record) {
                                if (Payment::query()
                                    ->where('invoice', $value)
                                    ->whereNot('id', $record->getKey())
                                    ->exists()
                                ) {
                                    $fail("Invoice $value already exists");
                                }
                            }),
                        Radio::make('payment_fee_id')
                            ->label('Payment Fee')
                            ->required()
                            ->options(
                                fn (Payment $record) => PaymentFee::type($record->type)
                                    ->active()
                                    ->get()
                                    ->mapWithKeys(fn (PaymentFee $paymentFee) => [$paymentFee->getKey() => $paymentFee->name])
                            )
                            ->descriptions(
                                fn (Payment $record) => PaymentFee::type($record->type)
                                    ->active()
                                    ->get()
                                    ->mapWithKeys(fn (PaymentFee $paymentFee) => [$paymentFee->getKey() => '('.$paymentFee->getFormattedFee().')'])
                            ),
                        Checkbox::make('dont_send_notification')
                            ->label(__('general.dont_send_notification')),
                    ])
                    ->action(function (Action $action, Payment $record, array $data) {
                        $paymentFeeId = data_get($data, 'payment_fee_id');

                        $paymentFee = PaymentFee::find($paymentFeeId);

                        $updateData = [
                            'payment_fee_id' => $paymentFeeId,
                            'amount' => $paymentFee->amount,
                            'currency' => $paymentFee->currency,
                        ];

                        if (array_key_exists('invoice', $data)) {
                            $updateData['invoice'] = $data['invoice'];
                        }

                        if(!$data['dont_send_notification']){
                            if($this->record->type == PaymentManager::TYPE_SUBMISSION_FEE){
                                $this->record?->user->notify(new SubmissionPayment($this->record->model));
                            } else {
                                $this->record?->user->notify(new ParticipantPayment($this->record->model));
                            }
                            
                        }

                        $record->update($updateData);

                        $action->successNotificationTitle('Payment Fee Updated');
                        $action->success();
                    }),
                Action::make('mark_as_paid')
                    ->label('Mark as Paid')
                    ->color('success')
                    ->authorize(fn (Payment $record) => auth()->user()->can('update', $record))
                    ->record($this->record)
                    ->requiresConfirmation()
                    ->form([
                        DateTimePicker::make('paid_at')
                            ->label('Paid At')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->displayFormat(Setting::get('format_date').' '.Setting::get('format_time')),
                    ])
                    ->action(function (Action $action, Payment $record, $data) {
                        $record->update([
                            'paid_at' => $data['paid_at'],
                        ]);

                        $action->successNotificationTitle('Payment Marked as Paid');
                        $action->success();
                    })
                    ->visible(fn (Payment $record) => ! $record->isPaid()),
            ])
                ->button()
                ->label('Actions')
                ->color('gray'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->record)
            ->columns(12)
            ->schema([
                Grid::make()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 8,
                    ])
                    ->schema([
                        Section::make('Information')
                            ->schema([
                                TextEntry::make('submission')
                                    ->visible(fn (Payment $record) => $record->type == PaymentManager::TYPE_SUBMISSION_FEE)
                                    ->state(fn (Payment $record) => $record->model?->getMeta('title') ?? '-')
                                    ->url(fn (Payment $record) => $record->model ? SubmissionResource::getUrl('view', ['record' => $record->model]) : null)
                                    ->color('primary'),
                                TextEntry::make('full_name')
                                    ->state(function (Payment $record) {
                                        if ($record->type == PaymentManager::TYPE_SUBMISSION_FEE) {
                                            return $record->user->full_name;
                                        }

                                        if ($record->type == PaymentManager::TYPE_PARTICIPANT_FEE) {
                                            return $record->model->full_name;
                                        }
                                    }),
                                TextEntry::make('email')
                                    ->state(function (Payment $record) {
                                        if ($record->type == PaymentManager::TYPE_SUBMISSION_FEE) {
                                            return $record->user->email;
                                        }

                                        if ($record->type == PaymentManager::TYPE_PARTICIPANT_FEE) {
                                            return $record->model->email;
                                        }
                                    }),
                                TextEntry::make('fee.name')
                                    ->label('Payment Fee Name'),
                                TextEntry::make('amount')
                                    ->state(fn ($record) => $record->getFormattedFee()),
                                ...PaymentFormItem::buildInfolistSchema($this->record->type),
                            ]),
                    ]),
                Grid::make()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 4,
                    ])
                    ->schema([
                        Section::make('Additional Information')
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Registered at')
                                    ->dateTime(Setting::get('format_date').' '.Setting::get('format_time')),
                                TextEntry::make('invoice')
                                    ->visible(fn (Payment $record) => app()->getCurrentScheduledConference()?->isInvoiceEnabled() && $record->invoice)
                                    ->state('Download')
                                    ->color('primary')
                                    ->url(fn (Payment $record) => Invoice::getUrl(['record' => $record]))
                                    ->openUrlInNewTab(),
                                TextEntry::make('paid_at')
                                    ->visible(fn (Payment $record) => $record->paid_at)
                                    ->dateTime(Setting::get('format_date').' '.Setting::get('format_time')),
                                TextEntry::make('receipt')
                                	->state('Download')
                                	->color('primary')
                                	->visible(fn(Payment $record) => app()->getCurrentScheduledConference()?->isReceiptEnabled() && $record->invoice && $record->paid_at)
                                	->url(fn(Payment $record) => Receipt::getUrl(['record' => $record]))
                                	->openUrlInNewTab(),
                            ]),
                        ...PaymentManager::get()->getPaymentMethodInfolist(),
                    ]),

            ]);
    }

    public static function getRoutePath(): string
    {
        return '/payments/detail/{record?}';
    }
}
