<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Infolists\Components\ShoutUpdateVersion;
use App\Infolists\Components\VerticalTabs;
use App\Panel\Administration\Livewire\PartnerTable;
use App\Panel\Administration\Livewire\SidebarSetting;
use App\Panel\Administration\Livewire\SponsorLevelTable;
use App\Panel\Administration\Livewire\SponsorTable;
use App\Panel\ScheduledConference\Livewire\NavigationMenuSetting;
use App\Panel\ScheduledConference\Livewire\AppearanceSetupSetting;
use App\Panel\ScheduledConference\Livewire\PrivacySetting;
use App\Panel\ScheduledConference\Livewire\SetupSetting;
use App\Panel\ScheduledConference\Livewire\ThemeSetting;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class WebsiteSetting extends Page
{
    protected static string $view = 'panel.scheduledConference.pages.website-setting';

    public static function getNavigationGroup(): string
    {
        return __('general.settings');
    }

    public function getHeading(): string|Htmlable
    {
        return __('general.website_setting');
    }

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    public static function getNavigationLabel(): string
    {
        return __('general.website');
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
                ShoutUpdateVersion::make('update-version'),
                Tabs::make()
                    ->contained(false)
                    ->tabs([
                        Tabs\Tab::make('Appearance')
                            ->label(__('general.appearance'))
                            ->schema([
                                VerticalTabs\Tabs::make()
                                    ->schema([
                                        VerticalTabs\Tab::make('Theme')
                                            ->label(__('general.theme'))
                                            ->icon('heroicon-o-adjustments-horizontal')
                                            ->schema([
                                                Livewire::make(ThemeSetting::class)->key('setup-setting'),
                                            ]),
                                        VerticalTabs\Tab::make('Appearance Setup')
                                            ->label(__('general.setup'))
                                            ->icon('heroicon-o-cog')
                                            ->schema([
                                                Livewire::make(AppearanceSetupSetting::class)
                                                    ->key('appearance-setup-setting')
                                                    ->lazy(),
                                            ]),
                                        VerticalTabs\Tab::make('Sidebar')
                                            ->label(__('general.sidebar'))
                                            ->icon('heroicon-o-view-columns')
                                            ->schema([
                                                Livewire::make(SidebarSetting::class)->key('sidebar-setting'),
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
                                                Livewire::make(NavigationMenuSetting::class)->key('navigation-menu-setting'),
                                            ]),
                                        VerticalTabs\Tab::make('Privacy Statement')
                                            ->label(__('general.privacy_statement'))
                                            ->icon('heroicon-o-shield-check')
                                            ->schema([
                                                Livewire::make(PrivacySetting::class)->key('navigation-menu-setting'),
                                            ]),
                                        VerticalTabs\Tab::make('Setup')
                                            ->label(__('general.setup'))
                                            ->icon('heroicon-o-cog')
                                            ->schema([
                                                Livewire::make(SetupSetting::class)
                                                    ->key('setup-setting')
                                                    ->lazy(),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
