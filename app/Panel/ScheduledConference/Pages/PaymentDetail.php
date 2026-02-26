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
use App\Notifications\PaymentConfirmed;
use App\Notifications\SubmissionPayment;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

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
                        'additional_item_keys' => collect($this->record->getMeta('additional_items', []))->pluck('key')->values()->all(),
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
                            ->live()
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
                        CheckboxList::make('additional_item_keys')
                            ->label('Add-on Items')
                            ->options(function (Get $get, Payment $record) {
                                $paymentFeeId = $get('payment_fee_id') ?: $record->payment_fee_id;
                                $paymentFee = PaymentFee::find($paymentFeeId);

                                return $paymentFee?->getAdditionalItemOptions() ?? [];
                            })
                            ->default(fn (Payment $record) => collect($record->getMeta('additional_items', []))->pluck('key')->values()->all())
                            ->visible(function (Get $get, Payment $record) {
                                $paymentFeeId = $get('payment_fee_id') ?: $record->payment_fee_id;
                                $paymentFee = PaymentFee::find($paymentFeeId);

                                return $paymentFee && count($paymentFee->getAdditionalItems()) > 0;
                            }),
                        Checkbox::make('dont_send_notification')
                            ->label(__('general.dont_send_notification')),
                    ])
                    ->action(function (Action $action, Payment $record, array $data) {
                        $paymentFeeId = data_get($data, 'payment_fee_id');

                        $paymentFee = PaymentFee::find($paymentFeeId);

                        $additionalItemKeys = data_get($data, 'additional_item_keys', []);
                        $selectedAdditionalItems = $paymentFee->resolveSelectedAdditionalItems($additionalItemKeys);
                        $totalAmount = $paymentFee->getAmountWithAdditionalItems($additionalItemKeys);

                        $updateData = [
                            'payment_fee_id' => $paymentFeeId,
                            'amount' => $totalAmount,
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
                        $record->setMeta('additional_items', $selectedAdditionalItems);
                        $record->setMeta('base_amount', $paymentFee->amount);

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

                        $record->user->notify(new PaymentConfirmed($record));
                    })
                    ->visible(fn (Payment $record) => ! $record->isPaid()),
                Action::make('mark_as_unpaid')
                    ->label('Mark as Unpaid')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (Payment $record) => auth()->user()->can('setUnpaid', $record))
                    ->record($this->record)
                    ->visible(fn(Payment $record) => $record->isPaid())
                    ->action(function(Action $action, Payment $record){
                        $record->update([
                            'paid_at' => null,
                        ]);

                        $action->successNotificationTitle('Payment Marked as Paid');
                        $action->success();
                    }),
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
                                TextEntry::make('base_amount')
                                    ->label('Base Fee')
                                    ->state(function (Payment $record) {
                                        $baseAmount = (float) $record->getMeta('base_amount', $record->fee?->amount ?? 0);

                                        return money($baseAmount, $record->currency, true)->formatWithoutZeroes();
                                    }),
                                TextEntry::make('additional_items')
                                    ->label('Add-on Items')
                                    ->visible(fn (Payment $record) => count($record->getMeta('additional_items', [])) > 0)
                                    ->state(function (Payment $record) {
                                        $lines = collect($record->getMeta('additional_items', []))
                                            ->map(function ($item) use ($record) {
                                                $name = data_get($item, 'name', '-');
                                                $amount = (float) data_get($item, 'amount', 0);
                                                $formatted = money($amount, $record->currency, true)->formatWithoutZeroes();

                                                return e($name).' ('.$formatted.')';
                                            });

                                        return new HtmlString($lines->implode('<br>'));
                                    })
                                    ->html(),
                                TextEntry::make('amount')
                                    ->label('Total Amount')
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
                                TextEntry::make('payment_method')
                                    ->visible(fn (Payment $record) => $record->payment_method)
                                    ->getStateUsing(fn($record) => Str::headline($record->payment_method)),
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
