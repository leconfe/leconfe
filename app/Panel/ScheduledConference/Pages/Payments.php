<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Facades\Hook;
use App\Infolists\Components\LivewireEntry;
use App\Infolists\Components\VerticalTabs as InfolistsVerticalTabs;
use App\Managers\PaymentManager;
use App\Panel\ScheduledConference\Livewire\ParticipantPaymentFeeTable;
use App\Panel\ScheduledConference\Livewire\Payment\ManualPaymentSetting;
use App\Panel\ScheduledConference\Livewire\PaymentFeeTable;
use App\Panel\ScheduledConference\Livewire\PaymentSetting;
use App\Panel\ScheduledConference\Livewire\SubmissionPaymentFeeTable;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

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
        return auth()->user()->can('update', app()->getCurrentScheduledConference());
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
                        Tabs\Tab::make('Settings')
                            ->schema([
                                LivewireEntry::make('submission_payment_settings')
                                    ->livewire(PaymentSetting::class),
                            ]),
                        Tabs\Tab::make('Submission Payment')
                            ->schema([
                                InfolistsVerticalTabs\Tabs::make()
                                    ->schema([
                                        InfolistsVerticalTabs\Tab::make('submission_fee_tab')
                                            ->label('Fees')
                                            ->schema([
                                                Livewire::make(PaymentFeeTable::class, ['paymentType' => PaymentManager::TYPE_SUBMISSION_FEE]),
                                            ]),
                                        InfolistsVerticalTabs\Tab::make('submission_fee_payments_tab')
                                            ->label('Payments')
                                            ->schema([
                                                LivewireEntry::make('payment_fees')
                                                    ->livewire(SubmissionPaymentFeeTable::class),

                                            ]),
                                    ]),

                            ]),
                        Tabs\Tab::make('Participant Payment')
                            ->schema([
                                InfolistsVerticalTabs\Tabs::make()
                                    ->schema([
                                        InfolistsVerticalTabs\Tab::make('participant_fee_tab')
                                            ->label('Fees')
                                            ->schema([
                                                Livewire::make(PaymentFeeTable::class, ['paymentType' => PaymentManager::TYPE_PARTICIPANT_FEE]),
                                            ]),
                                        InfolistsVerticalTabs\Tab::make('submission_fee_payments_tab')
                                            ->label('Payments')
                                            ->schema([
                                                LivewireEntry::make('participant_payment_fees')
                                                    ->livewire(ParticipantPaymentFeeTable::class),

                                            ]),
                                    ]),
                            ]),
                        Tabs\Tab::make('Payment Method')
                            ->schema([
                                InfolistsVerticalTabs\Tabs::make()
                                    ->schema($paymentMethodTabs),

                            ]),

                    ]),
            ]);
    }
}
