<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Infolists\Components\LivewireEntry;
use App\Infolists\Components\VerticalTabs as InfolistsVerticalTabs;
use App\Panel\ScheduledConference\Livewire\Registration\RegistrationSetting;
use App\Panel\ScheduledConference\Livewire\Registration\RegistrationTypeTable;
use App\Panel\ScheduledConference\Resources\RegistrantResource;
use Filament\Actions\Action;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class Registrations extends Page
{
    protected static string $view = 'panel.scheduledConference.pages.registration-setting';

    public static function getNavigationGroup(): string
    {
        return __('general.settings');
    }

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-end-on-rectangle';

    public static function getNavigationLabel(): string
    {
        return __('general.registrations');
    }

    public function getHeading(): string|Htmlable
    {
        return __('general.registrations_settings');
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('registrant')
                ->label(__('general.registrant_list'))
                ->color('gray')
                ->icon('heroicon-o-user-plus')
                ->outlined()
                ->url(RegistrantResource::getUrl('index')),
        ];
    }

    protected static ?string $title = 'Registration Settings';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user->can('RegistrationSetting:viewAny')) {
            return true;
        }

        return false;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistsVerticalTabs\Tabs::make()
                    ->schema([
                        InfolistsVerticalTabs\Tab::make('Settings')
                            ->label(__('general.settings'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                LivewireEntry::make('registrationPolicy')
                                    ->livewire(RegistrationSetting::class),
                            ]),
                        InfolistsVerticalTabs\Tab::make('Type')
                            ->label(__('general.type'))
                            ->icon('heroicon-o-list-bullet')
                            ->schema([
                                LivewireEntry::make('registrationType')
                                    ->livewire(RegistrationTypeTable::class),
                            ]),
                    ]),
            ]);
    }
}
