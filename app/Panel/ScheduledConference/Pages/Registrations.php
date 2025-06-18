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
use App\Panel\ScheduledConference\Livewire\RegistrationFormSetting;
use App\Panel\ScheduledConference\Livewire\RegistrationTable;
use App\Panel\ScheduledConference\Livewire\RegistrationTypeTable;
use App\Panel\ScheduledConference\Livewire\SubmissionPaymentFeeTable;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class Registrations extends Page
{
    protected static string $view = 'panel.scheduledConference.pages.registrations';

    public function mount() {}

    public static function getNavigationGroup(): string
    {
        return __('general.settings');
    }

    public static function getNavigationLabel(): string
    {
        return "Registrations";
    }

    public function getHeading(): string|Htmlable
    {
        return "Registrations";
    }

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()->can('update', app()->getCurrentScheduledConference());
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->id('payments')
            ->schema([
                Tabs::make('Tabs')
                    ->contained(false)
                    ->tabs([
                        Tabs\Tab::make('Registrations')
                            ->schema([
                                Livewire::make(RegistrationTable::class)
                                    ->key('registration_table'),
                            ]),
                        Tabs\Tab::make('Settings')
                            ->schema([
                                InfolistsVerticalTabs\Tabs::make()
                                ->schema([
                                    InfolistsVerticalTabs\Tab::make('Form')
                                        ->label('Form')
                                        ->schema([
                                            Livewire::make(RegistrationFormSetting::class)
                                                ->key('registration_form_setting'),
                                        ]),
                                ]),
                              
                                // Livewire::make(RegistrationTypeTable::class)
                                //     ->key('registration_type_table'),
                            ]),
                    ]),
            ]);
    }
}
