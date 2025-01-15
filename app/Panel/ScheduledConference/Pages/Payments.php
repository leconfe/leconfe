<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Facades\Hook;
use App\Infolists\Components\LivewireEntry;
use App\Infolists\Components\VerticalTabs as InfolistsVerticalTabs;
use App\Managers\PaymentManager;
use App\Models\Enums\PaymentType;
use App\Panel\ScheduledConference\Livewire\Payment\ManualPaymentSetting;
use App\Panel\ScheduledConference\Livewire\Payment\PaymentSetting;
use App\Panel\ScheduledConference\Livewire\PaymentFeeTable;
use App\Panel\ScheduledConference\Livewire\SubmissionPaymentFeeTable;
use App\Panel\ScheduledConference\Livewire\SubmissionPaymentSetting;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Infolists\Components\Tabs;



class Payments extends Page
{
    protected static string $view = 'panel.scheduledConference.pages.payment';

    public function mount() {}

    public static function getNavigationGroup(): string
    {
        return __('general.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('general.payments');
    }

    public function getHeading(): string|Htmlable
    {
        return __('general.payments');
    }

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()->can('PaymentSetting:viewAny');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $paymentMethodTabs = [
            InfolistsVerticalTabs\Tab::make('Manual')
                ->label(__('general.manual'))
                ->icon('heroicon-o-credit-card')
                ->schema([
                    LivewireEntry::make('manual')
                        ->livewire(ManualPaymentSetting::class),
                ]),
        ];

        Hook::call('Payments::PaymentMethodTabs', [&$paymentMethodTabs, $this]);

        return $infolist
            ->id('payments')
            ->schema([
                Tabs::make('Tabs')
                    ->contained(false)
                    ->tabs([
                        Tabs\Tab::make('Submission Payment')
                            ->schema([
                                InfolistsVerticalTabs\Tabs::make()
                                    ->schema([
                                        InfolistsVerticalTabs\Tab::make('submission_payment_tab')
                                            ->label("Settings")
                                            ->schema([
                                                LivewireEntry::make('submission_payment_settings')
                                                    ->livewire(SubmissionPaymentSetting::class),
                                            ]),
                                        InfolistsVerticalTabs\Tab::make('submission_fee_tab')
                                            ->label("Payment Fees")
                                            ->schema([
                                                LivewireEntry::make('payment_fees')
                                                    ->livewire(PaymentFeeTable::class, ['paymentType' => PaymentManager::TYPE_SUBMISSION_FEE]),
                                            ]),
                                        InfolistsVerticalTabs\Tab::make('submission_fee_payments_tab')
                                            ->label("Submission Fee Payment")
                                            ->schema([
                                                LivewireEntry::make('payment_fees')
                                                    ->livewire(SubmissionPaymentFeeTable::class),

                                            ]),
                                    ]),

                            ]),
                        Tabs\Tab::make('Attendances Payment')
                            ->schema([
                                // ...
                            ]),
                        Tabs\Tab::make('Payment Method')
                            ->schema([
                                InfolistsVerticalTabs\Tabs::make()
                                    ->schema($paymentMethodTabs),

                            ]),

                    ])
            ]);
    }
}
