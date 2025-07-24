<?php

namespace App\Panel\Conference\Pages;

use App\Infolists\Components\ShoutUpdateVersion;
use App\Infolists\Components\VerticalTabs;
use App\Panel\Administration\Livewire\LanguageSetting;
use App\Panel\Administration\Livewire\SidebarSetting;
use App\Panel\Conference\Livewire\DateAndTimeSetting;
use App\Panel\Conference\Livewire\NavigationMenuSetting;
use App\Panel\Conference\Livewire\SetupSetting;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class WebsiteSetting extends Page
{
    protected static string $view = 'panel.conference.pages.website-setting';

    public static function getNavigationGroup(): string
    {
        return __('general.settings');
    }

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    public static function getNavigationLabel(): string
    {
        return __('general.website');
    }

    public function getHeading(): string|Htmlable
    {
        return __('general.website_setting');
    }

    public function mount(): void
    {
        $this->authorize('update', App::getCurrentConference());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()->can('update', App::getCurrentConference());
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                ShoutUpdateVersion::make('update-version'),
                Tabs::make()
                    ->contained(false)
                    ->tabs([
                        Tabs\Tab::make('Appearance')
                            ->label(__('general.appearance'))
                            ->schema([
                                VerticalTabs\Tabs::make()
                                    ->schema([
                                        VerticalTabs\Tab::make('Setup')
                                            ->label(__('general.setup'))
                                            ->icon('heroicon-o-cog')
                                            ->schema([
                                                Livewire::make(SetupSetting::class)
                                                    ->lazy(),
                                            ]),
                                        VerticalTabs\Tab::make('Sidebar')
                                            ->label(__('general.sidebar'))
                                            ->icon('heroicon-o-view-columns')
                                            ->schema([
                                                Livewire::make(SidebarSetting::class),
                                            ]),
                                    ]),
                            ]),
                        Tabs\Tab::make('Setup')
                            ->label(__('general.setup'))
                            ->schema([
                                VerticalTabs\Tabs::make()
                                    ->schema([
                                        VerticalTabs\Tab::make('Navigation Menu')
                                            ->label(__('general.navigation_menu'))
                                            ->icon('heroicon-o-list-bullet')
                                            ->schema([
                                                Livewire::make(NavigationMenuSetting::class),
                                            ]),
                                        VerticalTabs\Tab::make('Languages')
                                            ->label(__('general.languages'))
                                            ->icon('heroicon-o-language')
                                            ->schema([
                                                Livewire::make(LanguageSetting::class),
                                            ]),
                                        VerticalTabs\Tab::make('Date & Time')
                                            ->label(__('general.date_and_time'))
                                            ->icon('heroicon-o-clock')
                                            ->schema([
                                                Livewire::make(DateAndTimeSetting::class)
                                                    ->lazy(),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
