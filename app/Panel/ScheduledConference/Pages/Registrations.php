<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Infolists\Components\VerticalTabs as InfolistsVerticalTabs;
use App\Panel\ScheduledConference\Livewire\InvoiceSetting;
use App\Panel\ScheduledConference\Livewire\RegistrationFormTable;
use App\Panel\ScheduledConference\Livewire\RegistrationSetting;
use App\Panel\ScheduledConference\Livewire\RegistrationTable;
use App\Panel\ScheduledConference\Livewire\RegistrationTypeTable;
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
        return __('scheduled_conference.registrations');
    }

    public function getHeading(): string|Htmlable
    {
        return __('scheduled_conference.registrations');
    }

    protected static ?string $navigationIcon = 'heroicon-o-users';

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
                        Tabs\Tab::make('settings')
                            ->label(__('scheduled_conference.settings'))
                            ->schema([
                                InfolistsVerticalTabs\Tabs::make()
                                    ->schema([
                                        InfolistsVerticalTabs\Tab::make('General')
                                            ->label(__('scheduled_conference.general'))
                                            ->schema([
                                                Livewire::make(RegistrationSetting::class)
                                                    ->key('registration_form_setting'),
                                            ]),
                                        InfolistsVerticalTabs\Tab::make('Type')
                                            ->schema([
                                                Livewire::make(RegistrationTypeTable::class)
                                                    ->key('registration_type_table'),
                                            ]),
                                        InfolistsVerticalTabs\Tab::make('Form')
                                            ->label(__('general.form'))
                                            ->schema([
                                                Livewire::make(RegistrationFormTable::class)
                                                    ->key('registration_form_table'),
                                            ]),
                                        InfolistsVerticalTabs\Tab::make('Invoice')
                                            ->label(__('scheduled_conference.invoice'))
                                            ->schema([
                                                Livewire::make(InvoiceSetting::class)
                                                    ->key('invoice_setting'),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
