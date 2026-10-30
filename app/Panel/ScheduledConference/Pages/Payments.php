<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Facades\Hook;
use App\Infolists\Components\VerticalTabs as InfolistsVerticalTabs;
use App\Managers\PaymentManager;
use App\Panel\ScheduledConference\Livewire\InvoiceSetting;
use App\Panel\ScheduledConference\Livewire\ParticipantPaymentFeeTable;
use App\Panel\ScheduledConference\Livewire\Payment\ManualPaymentSetting;
use App\Panel\ScheduledConference\Livewire\PaymentFeeTable;
use App\Panel\ScheduledConference\Livewire\PaymentFormItemTable;
use App\Panel\ScheduledConference\Livewire\PaymentSetting;
use App\Panel\ScheduledConference\Livewire\SubmissionPaymentTable;
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

    public function getTitle(): string
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
                    Livewire::make(ManualPaymentSetting::class),
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
                            ->label(__('general.submission_payment'))
                            ->schema([
                                Livewire::make(SubmissionPaymentTable::class),
                            ]),
                        Tabs\Tab::make('Participant Payment')
                            ->label(__('general.participant_payment'))
                            ->schema([
                                Livewire::make(ParticipantPaymentFeeTable::class),
                            ]),
                        Tabs\Tab::make('Settings')
                            ->label(__('general.settings'))
                            ->schema([
                                InfolistsVerticalTabs\Tabs::make()
                                    ->schema([
                                        InfolistsVerticalTabs\Tab::make('General')
                                            ->label(__('general.general'))
                                            ->schema([
                                                Livewire::make(PaymentSetting::class)
                                                    ->key('payment_settings'),
                                            ]),
                                        InfolistsVerticalTabs\Tab::make('Submission Fees')
                                            ->label(__('general.submission_fees'))
                                            ->schema([
                                                Tabs::make()
                                                    ->contained(false)
                                                    ->tabs([
                                                        Tabs\Tab::make('Fees')
                                                            ->label(__('general.fees'))
                                                            ->schema([
                                                                Livewire::make(PaymentFeeTable::class, ['paymentType' => PaymentManager::TYPE_SUBMISSION_FEE])
                                                                    ->key('submission_payment_fees'),
                                                            ]),
                                                        Tabs\Tab::make('Form')
                                                            ->label(__('general.form'))
                                                            ->schema([
                                                                Livewire::make(PaymentFormItemTable::class, ['paymentType' => PaymentManager::TYPE_SUBMISSION_FEE])
                                                                    ->key('submission_payment_form_item'),

                                                            ]),
                                                    ])
                                            ]),
                                        InfolistsVerticalTabs\Tab::make('Participant Fees')
                                            ->label(__('general.participant_fees'))
                                            ->schema([
                                                Tabs::make()
                                                    ->contained(false)
                                                    ->tabs([
                                                        Tabs\Tab::make('Fees')
                                                            ->label(__('general.fees'))
                                                            ->schema([
                                                                Livewire::make(PaymentFeeTable::class, ['paymentType' => PaymentManager::TYPE_PARTICIPANT_FEE])
                                                                    ->key('participant_payment_fees'),
                                                            ]),
                                                        Tabs\Tab::make('Form')
                                                            ->label(__('general.form'))
                                                            ->schema([
                                                                Livewire::make(PaymentFormItemTable::class, ['paymentType' => PaymentManager::TYPE_PARTICIPANT_FEE])
                                                                    ->key('participant_payment_form_item'),

                                                            ]),
                                                    ]),
                                            ]),
                                        InfolistsVerticalTabs\Tab::make('Invoice')
                                            ->label(__('general.invoice'))
                                            ->schema([
                                                Livewire::make(InvoiceSetting::class)->key('invoice'),
                                            ]),
                                    ]),
                            ]),
                        Tabs\Tab::make('Payment Method')
                            ->label(__('general.payment_method'))
                            ->schema([
                                InfolistsVerticalTabs\Tabs::make()
                                    ->schema($paymentMethodTabs),
                            ]),

                    ]),
            ]);
    }
}
