<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Facades\Setting;
use App\Managers\PaymentManager;
use App\Models\Enums\SubmissionStatus;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Models\PaymentFormItem;
use App\Models\Submission;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Squire\Models\Country;

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
                    ->visible(fn (Payment $record) => ! $record->isPaid() && static::canUsePaymentMethodActions($record))
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
                Action::make('edit_participant_information')
                    ->label(__('general.edit_participant_information'))
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->record($this->record)
                    ->visible(fn (Payment $record) => static::canManageParticipantInformation($record))
                    ->authorize(fn (Payment $record) => static::canManageParticipantInformation($record))
                    ->disabled(fn (Payment $record) => static::participantInformationIsLocked($record))
                    ->tooltip(fn (Payment $record) => static::participantInformationIsLocked($record)
                        ? __('general.participant_information_locked')
                        : null)
                    ->modalWidth('2xl')
                    ->fillForm(function (Payment $record) {
                        $participant = $record->model;

                        return $participant instanceof Participant
                            ? ['meta' => Arr::only($participant->getAllMeta()->toArray(), static::participantMetaKeys())]
                            : [];
                    })
                    ->form([
                        TextInput::make('meta.affiliation')
                            ->label(__('general.affiliation')),
                        TextInput::make('meta.address_line')
                            ->label(__('general.address_line'))
                            ->columnSpanFull(),
                        TextInput::make('meta.post_code')
                            ->label(__('general.post_code')),
                        TextInput::make('meta.city')
                            ->label(__('general.city')),
                        Select::make('meta.country')
                            ->label(__('general.country'))
                            ->placeholder(__('general.select_a_country'))
                            ->searchable()
                            ->options(fn () => Country::all()->mapWithKeys(fn ($country) => [$country->id => $country->flag.' '.$country->name]))
                            ->optionsLimit(250),
                    ])
                    ->action(function (Action $action, Payment $record, array $data) {
                        abort_unless(static::canManageParticipantInformation($record), 403);

                        $updated = DB::transaction(function () use ($record, $data) {
                            $lockedPayment = Payment::query()
                                ->lockForUpdate()
                                ->findOrFail($record->getKey());

                            abort_unless(static::canManageParticipantInformation($lockedPayment), 403);

                            if (static::participantInformationIsLocked($lockedPayment)) {
                                return false;
                            }

                            $participant = $lockedPayment->model;
                            abort_unless($participant instanceof Participant, 404);

                            $participant->setManyMeta(Arr::only(
                                data_get($data, 'meta', []),
                                static::participantMetaKeys(),
                            ));

                            return true;
                        });

                        if (! $updated) {
                            $action->failureNotificationTitle(__('general.participant_information_locked'));
                            $action->failure();

                            return;
                        }

                        $this->record->refresh()->load('model');
                        $action->successNotificationTitle(__('general.participant_information_updated'));
                        $action->success();
                    }),
                Action::make('create_invoice')
                    ->label(__('general.create_invoice'))
                    ->icon('heroicon-o-document-plus')
                    ->color('gray')
                    ->authorize(fn (?Payment $record) => $record ? auth()->user()->can('update', $record) : false)
                    ->record($this->record)
                    ->visible(fn (Payment $record) => static::canCreateInvoiceFor($record))
                    ->requiresConfirmation()
                    ->action(function (Action $action, Payment $record) {
                        static::createInvoiceFor($action, $record);
                    }),
                Action::make('send_invoice')
                    ->label(__('general.send_invoice'))
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->authorize(fn (?Payment $record) => $record ? auth()->user()->can('update', $record) : false)
                    ->record($this->record)
                    ->visible(fn (Payment $record) => static::canSendInvoiceFor($record))
                    ->requiresConfirmation()
                    ->action(function (Action $action, Payment $record) {
                        static::sendInvoiceFor($action, $record);
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
                    ->visible(fn (Payment $record) => ! $record->isPaid() && static::canUsePaymentMethodActions($record)),
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

        if (static::shouldSendParticipantPaymentNotificationFor($record, $data)) {
            $participant = $record->model;

            if ($participant && $record->user) {
                $record->ensureInvoice();
                $record->user->notify(new ParticipantPayment($record->getKey()));
                $record->markInvoiceAsSent();
            }
        }
    }

    public static function canUsePaymentMethodActions(Payment $record): bool
    {
        if ($record->type !== PaymentManager::TYPE_SUBMISSION_FEE) {
            return true;
        }

        $submission = $record->model;

        if (! $submission instanceof Submission) {
            return false;
        }

        return ! in_array($submission->status, [
            SubmissionStatus::Declined,
            SubmissionStatus::Withdrawn,
        ], true);
    }

    public static function canSendInvoiceFor(Payment $record): bool
    {
        return ! $record->isPaid()
            && (bool) $record->scheduledConference?->isInvoiceEnabled();
    }

    public static function canCreateInvoiceFor(Payment $record): bool
    {
        return ! $record->isPaid()
            && blank($record->invoice)
            && (bool) $record->scheduledConference?->isInvoiceEnabled();
    }

    public static function createInvoiceFor(Action $action, Payment $record): void
    {
        if (! static::canCreateInvoiceFor($record) || ! $record->ensureInvoice()) {
            $action->failureNotificationTitle(__('general.failed_create_invoice'));
            $action->failure();

            return;
        }

        $action->successNotificationTitle(__('general.invoice_created_successfully'));
        $action->success();
    }

    public static function sendInvoiceFor(Action $action, Payment $record): void
    {
        $record->ensureInvoice();

        if ($record->type == PaymentManager::TYPE_SUBMISSION_FEE) {
            static::sendSubmissionInvoiceFor($action, $record);

            return;
        }

        if ($record->type == PaymentManager::TYPE_PARTICIPANT_FEE) {
            static::sendParticipantInvoiceFor($action, $record);

            return;
        }

        static::failSendInvoice($action);
    }

    protected static function sendSubmissionInvoiceFor(Action $action, Payment $record): void
    {
        $record = $record->refresh();
        $submission = $record->model;

        if (! $submission instanceof Submission || ! $submission->user) {
            static::failSendInvoice($action);

            return;
        }

        $submission->user->notify(new SubmissionPayment($record->getKey()));
        $record->markInvoiceAsSent();

        static::successSendInvoice($action);
    }

    protected static function sendParticipantInvoiceFor(Action $action, Payment $record): void
    {
        $record = $record->refresh();
        $participant = $record->model;

        if (! $participant instanceof Participant || ! $participant->email) {
            static::failSendInvoice($action);

            return;
        }

        $participant->notify(new ParticipantPayment($record->getKey()));
        $record->markInvoiceAsSent();

        static::successSendInvoice($action);
    }

    protected static function failSendInvoice(Action $action): void
    {
        $action->failureNotificationTitle(__('general.failed_send_notification'));
        $action->failure();
    }

    protected static function successSendInvoice(Action $action): void
    {
        $action->successNotificationTitle(__('general.invoice_sent_successfully'));
        $action->success();
    }

    protected function shouldSendParticipantPaymentNotification(Payment $record, array $data): bool
    {
        return static::shouldSendParticipantPaymentNotificationFor($record, $data);
    }

    protected static function shouldSendParticipantPaymentNotificationFor(Payment $record, array $data): bool
    {
        if ($record->type != PaymentManager::TYPE_PARTICIPANT_FEE) {
            return false;
        }

        return ! data_get($data, 'dont_send_notification', false);
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
                        Section::make(__('general.participant_information'))
                            ->visible(fn (Payment $record) => $record->type == PaymentManager::TYPE_PARTICIPANT_FEE && $record->model instanceof Participant)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('participant_affiliation')
                                            ->label(__('general.affiliation'))
                                            ->state(fn (Payment $record) => $record->model->getMeta('affiliation'))
                                            ->placeholder('-'),
                                        TextEntry::make('participant_country')
                                            ->label(__('general.country'))
                                            ->state(fn (Payment $record) => Country::find($record->model->getMeta('country'))?->name)
                                            ->placeholder('-'),
                                        TextEntry::make('participant_address_line')
                                            ->label(__('general.address_line'))
                                            ->state(fn (Payment $record) => $record->model->getMeta('address_line'))
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                        TextEntry::make('participant_post_code')
                                            ->label(__('general.post_code'))
                                            ->state(fn (Payment $record) => $record->model->getMeta('post_code'))
                                            ->placeholder('-'),
                                        TextEntry::make('participant_city')
                                            ->label(__('general.city'))
                                            ->state(fn (Payment $record) => $record->model->getMeta('city'))
                                            ->placeholder('-'),
                                    ]),
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
                                TextEntry::make('invoice_email_status')
                                    ->label(__('general.invoice_email'))
                                    ->visible(fn (Payment $record) => app()->getCurrentScheduledConference()?->isInvoiceEnabled())
                                    ->badge()
                                    ->state(function (Payment $record) {
                                        $sentAt = $record->getInvoiceSentAt();

                                        return $sentAt
                                            ? __('general.invoice_sent_at', [
                                                'date' => $sentAt->format(Setting::get('format_date').' '.Setting::get('format_time')),
                                            ])
                                            : __('general.not_sent');
                                    })
                                    ->color(fn (Payment $record) => $record->hasInvoiceBeenSent() ? 'success' : 'gray'),
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

    protected static function participantMetaKeys(): array
    {
        return ['affiliation', 'address_line', 'post_code', 'city', 'country'];
    }

    protected static function canManageParticipantInformation(Payment $record): bool
    {
        if ($record->type != PaymentManager::TYPE_PARTICIPANT_FEE) {
            return false;
        }

        $participant = $record->model;
        $scheduledConference = $record->scheduledConference;

        return $participant instanceof Participant
            && $participant->scheduled_conference_id == $record->scheduled_conference_id
            && $scheduledConference
            && auth()->user()?->can('update', $scheduledConference);
    }

    protected static function participantInformationIsLocked(Payment $record): bool
    {
        return filled($record->invoice)
            || $record->hasInvoiceBeenSent()
            || $record->isPaid();
    }
}
