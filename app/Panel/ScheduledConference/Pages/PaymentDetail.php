<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Facades\Setting;
use App\Managers\PaymentManager;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Models\PaymentFormItem;
use App\Notifications\ParticipantPayment;
use App\Notifications\PaymentConfirmed;
use App\Notifications\SubmissionPayment;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Awcodes\Shout\Components\ShoutEntry;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Checkbox;
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
                    ->disabled(fn (Payment $record) => ! $record->isPaid() && ! (app()->getCurrentScheduledConference()?->isPaymentOpen() ?? true))
            );

        return [
            ActionGroup::make($paymentActions->toArray())
                ->button()
                ->label('Payment'),
            ActionGroup::make([
                Action::make('edit_payment')
                    ->label('Edit Payment')
                    ->visible(function (Payment $record) {
                        if ($record->type == PaymentManager::TYPE_SUBMISSION_FEE && ! app()->getCurrentScheduledConference()->isSubmissionPaymentEnabled()) {
                            return false;
                        }
                        if ($record->type == PaymentManager::TYPE_PARTICIPANT_FEE && ! app()->getCurrentScheduledConference()->isParticipantPaymentEnabled()) {
                            return false;
                        }

                        return auth()->user()->can('update', $record) && ! $record->isPaid();
                    })
                    ->color('gray')
                    ->record($this->record)
                    ->fillForm(function () {
                        $additionalItemsData = [];
                        $existingItems = $this->record->getMeta('additional_items', []);

                        if ($this->record->fee) {
                            foreach ($this->record->fee->getAdditionalItems() as $item) {
                                $key = $item['key'];
                                $existingItem = collect($existingItems)->firstWhere('key', $key);
                                $additionalItemsData[$key] = $existingItem ? (int) data_get($existingItem, 'quantity', 0) : 0;
                            }
                        }

                        return [
                            'invoice' => $this->record->invoice,
                            'payment_fee_id' => $this->record->payment_fee_id,
                            'additional_items' => $additionalItemsData,
                        ];
                    })
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
                        \Filament\Forms\Components\Fieldset::make('Add-on Items')
                            ->schema(function (Get $get, ?Payment $record) {
                                $record ??= $this->record;
                                if (! $record) {
                                    return [];
                                }

                                $paymentFeeId = $get('payment_fee_id') ?: $record->payment_fee_id;
                                $paymentFee = PaymentFee::find($paymentFeeId);

                                if (! $paymentFee) {
                                    return [];
                                }

                                $existingItems = $record->getMeta('additional_items', []);

                                return collect($paymentFee->getAdditionalItems())->map(function ($item) use ($paymentFee, $existingItems) {
                                    $formattedAmount = money($item['amount'], $paymentFee->currency, true)->formatWithoutZeroes();
                                    $existingItem = collect($existingItems)->firstWhere('key', $item['key']);
                                    $defaultValue = $existingItem ? (int) data_get($existingItem, 'quantity', 0) : 0;

                                    return \App\Forms\Components\AddOnItemCounter::make("additional_items.{$item['key']}")
                                        ->label("{$item['name']} ({$formattedAmount})")
                                        ->helperText($item['description'] ?? null)
                                        ->minValue(0)
                                        ->maxValue(999)
                                        ->default($defaultValue);
                                })->toArray();
                            })
                            ->columns(1)
                            ->visible(function (Get $get, ?Payment $record) {
                                $record ??= $this->record;
                                if (! $record) {
                                    return false;
                                }

                                $paymentFeeId = $get('payment_fee_id') ?: $record->payment_fee_id;
                                $paymentFee = PaymentFee::find($paymentFeeId);

                                return $paymentFee && count($paymentFee->getAdditionalItems()) > 0;
                            }),
                        Checkbox::make('dont_send_notification')
                            ->visible(fn () => $this->record?->type == PaymentManager::TYPE_PARTICIPANT_FEE)
                            ->label(__('general.dont_send_notification')),
                    ])
                    ->action(function (Action $action, Payment $record, array $data) {
                        static::updatePaymentFeeRecord($record, $data);

                        $action->successNotificationTitle('Payment Fee Updated');
                        $action->success();
                    }),
                Action::make('resend_submission_invoice_email')
                    ->label('Resend Submission Invoice Email')
                    ->color('gray')
                    ->authorize(fn (?Payment $record) => $record ? auth()->user()->can('update', $record) : false)
                    ->visible(fn (?Payment $record) => $record?->type == PaymentManager::TYPE_SUBMISSION_FEE)
                    ->requiresConfirmation()
                    ->action(function (Action $action, Payment $record) {
                        $submission = $record->model;

                        if (! $submission || ! $record->user) {
                            $action->failureNotificationTitle(__('general.failed_send_notification'));
                            $action->failure();

                            return;
                        }

                        $record->ensureInvoice();
                        $submission->setRelation('payment', $record->refresh());
                        $record->user->notify(new SubmissionPayment($submission));

                        $action->successNotificationTitle('Submission invoice email resent');
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
                        $scheduledConference = $record->scheduledConference;
                        $updateData = [
                            'paid_at' => $data['paid_at'],
                        ];

                        if ($scheduledConference?->isReceiptEnabled() && ! $record->receipt) {
                            $receiptNumber = $scheduledConference->getLatestReceiptNumber();
                            $updateData['receipt'] = $scheduledConference->generateReceiptNumber($receiptNumber);
                            $scheduledConference->updateLatestReceiptNumber($receiptNumber + 1);
                        }

                        $record->update($updateData);

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
                    ->visible(fn (Payment $record) => $record->isPaid())
                    ->action(function (Action $action, Payment $record) {
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

    public static function updatePaymentFeeRecord(Payment $record, array $data): void
    {
        $paymentFeeId = data_get($data, 'payment_fee_id');
        $paymentFee = PaymentFee::findOrFail($paymentFeeId);

        $additionalItems = data_get($data, 'additional_items', []);
        $selectedAdditionalItems = $paymentFee->getSelectedAdditionalItemsFromData(['additional_items' => $additionalItems]);
        $totalAmount = $paymentFee->getAmountWithAdditionalItemsFromData(['additional_items' => $additionalItems]);

        $updateData = [
            'payment_fee_id' => $paymentFeeId,
            'amount' => $totalAmount,
            'currency' => $paymentFee->currency,
        ];

        if (array_key_exists('invoice', $data)) {
            $updateData['invoice'] = $data['invoice'];
        }

        $record->update($updateData);
        $record->setMeta('additional_items', $selectedAdditionalItems);
        $record->setMeta('base_amount', $paymentFee->amount);

        if (! data_get($data, 'dont_send_notification', false) && $record->type == PaymentManager::TYPE_PARTICIPANT_FEE) {
            $participant = $record->model;

            if ($participant) {
                $record->ensureInvoice();
                $participant->setRelation('payment', $record->refresh());
                $record->user?->notify(new ParticipantPayment($participant));
            }
        }
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
                                                $quantity = (int) data_get($item, 'quantity', 1);
                                                $amount = (float) data_get($item, 'total_amount', data_get($item, 'amount', 0));
                                                $formatted = money($amount, $record->currency, true)->formatWithoutZeroes();

                                                $text = e($name);
                                                if ($quantity > 1) {
                                                    $text .= " x{$quantity}";
                                                }
                                                $text .= ' ('.$formatted.')';

                                                return $text;
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
                        ShoutEntry::make('payment_availability_notice')
                            ->visible(fn (Payment $record) => ! $record->isPaid() && $this->shouldShowPaymentAvailabilitySection())
                            ->heading(fn () => $this->getPaymentAvailabilityHeading())
                            ->type(fn () => $this->getPaymentAvailabilityType())
                            ->columnSpan('full')
                            ->content(fn () => new HtmlString($this->getPaymentAvailabilityContent())),
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
                                    ->getStateUsing(fn ($record) => Str::headline($record->payment_method)),
                                TextEntry::make('receipt')
                                    ->label(fn (Payment $record) => $record->receipt ? "Receipt No: {$record->receipt}" : 'Receipt')
                                    ->state('Download')
                                    ->color('primary')
                                    ->visible(fn (Payment $record) => app()->getCurrentScheduledConference()?->isReceiptEnabled() && $record->receipt && $record->paid_at)
                                    ->url(fn (Payment $record) => Receipt::getUrl(['record' => $record]))
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

    protected function shouldShowPaymentAvailabilitySection(): bool
    {
        return filled($this->getPaymentAvailabilityContent());
    }

    protected function getPaymentAvailabilityHeading(): string
    {
        $scheduledConference = app()->getCurrentScheduledConference();

        if (! $scheduledConference) {
            return __('general.payment_is_available');
        }

        if (! $scheduledConference->isPaymentOpen()) {
            $openedAt = $scheduledConference->getPaymentOpenedAt();
            $closedAt = $scheduledConference->getPaymentClosedAt();

            if ($openedAt && now()->lt($openedAt)) {
                return __('general.payment_is_not_open_yet');
            }

            if ($closedAt && now()->gt($closedAt)) {
                return __('general.payment_period_has_ended');
            }
        }

        return __('general.payment_is_available');
    }

    protected function getPaymentAvailabilityType(): string
    {
        return app()->getCurrentScheduledConference()?->isPaymentOpen() ? 'success' : 'warning';
    }

    protected function getPaymentAvailabilityContent(): string
    {
        $scheduledConference = app()->getCurrentScheduledConference();

        if (! $scheduledConference) {
            return '';
        }

        $openedAt = $scheduledConference->getPaymentOpenedAt();
        $closedAt = $scheduledConference->getPaymentClosedAt();
        $format = Setting::get('format_date');

        if (! $scheduledConference->isPaymentOpen()) {
            if ($openedAt && now()->lt($openedAt)) {
                if ($closedAt) {
                    return __('general.payment_will_open_on_and_close_on', [
                        'start' => e($openedAt->format($format)),
                        'end' => e($closedAt->format($format)),
                    ]);
                }

                return __('general.payment_will_open_on', [
                    'date' => e($openedAt->format($format)),
                ]);
            }

            if ($closedAt && now()->gt($closedAt)) {
                if ($openedAt) {
                    return __('general.payment_was_available_from_until', [
                        'start' => e($openedAt->format($format)),
                        'end' => e($closedAt->format($format)),
                    ]);
                }

                return __('general.payment_period_ended_on', [
                    'date' => e($closedAt->format($format)),
                ]);
            }

            return __('general.payment_is_not_available_at_this_time');
        }

        if ($closedAt) {
            return __('general.payment_is_available_until', [
                'date' => e($closedAt->format($format)),
            ]);
        }

        if ($openedAt) {
            return __('general.payment_is_currently_available_starting', [
                'date' => e($openedAt->format($format)),
            ]);
        }

        return '';
    }
}
