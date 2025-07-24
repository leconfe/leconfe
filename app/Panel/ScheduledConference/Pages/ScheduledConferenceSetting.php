<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Panel\ScheduledConference\Livewire\ContactSetting;
use App\Panel\ScheduledConference\Livewire\MastHeadSetting;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class ScheduledConferenceSetting extends Page
{
    protected static string $view = 'panel.scheduledConference.pages.scheduled-conference-setting';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    public static function getNavigationLabel(): string
    {
        return __('general.scheduled_conference_setting');
    }

    public function getHeading(): string|Htmlable
    {
        return __('general.scheduled_conference');
    }

    public static function getNavigationGroup(): string
    {
        return __('general.settings');
    }

    public function mount(): void
    {
        $this->authorize('update', App::getCurrentScheduledConference());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()->can('update', App::getCurrentScheduledConference());
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make()
                    ->contained(false)
                    ->tabs([
                        Tabs\Tab::make('Masthead')
                            ->label(__('general.masthead'))
                            ->schema([
                                Livewire::make(MastHeadSetting::class),
                            ]),
                        Tabs\Tab::make(__('general.contact'))
                            ->schema([
                                Livewire::make(ContactSetting::class),
                            ]),
                    ]),
            ]);
    }
}
