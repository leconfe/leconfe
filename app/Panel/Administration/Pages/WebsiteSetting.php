<?php

namespace App\Panel\Administration\Pages;

use App\Infolists\Components\ShoutUpdateVersion;
use App\Infolists\Components\VerticalTabs;
use App\Panel\Administration\Livewire\FeaturedScheduledConferenceTable;
use App\Panel\Administration\Livewire\LanguageSetting;
use App\Panel\Administration\Livewire\SetupSetting;
use App\Panel\Administration\Livewire\SidebarSetting;
use App\Panel\Conference\Livewire\NavigationMenuSetting;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class WebsiteSetting extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static ?string $navigationIcon = 'heroicon-m-cog';

    protected static string $view = 'panel.administration.pages.site-settings';

    public static function getNavigationLabel(): string
    {
        return __('general.website_setting');
    }

    public function getHeading(): string|Htmlable
    {
        return __('general.website_setting');
    }

    public static function getNavigationGroup(): string
    {
        return __('general.settings');
    }

    public function mount() {}

    public static function canAccess(): bool
    {
        return Auth::user()->can('update', app()->getSite());
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                ShoutUpdateVersion::make('update-version'),
                Tabs::make('site_settings')
                    ->tabs([
                        Tabs\Tab::make('Site Setup')
                            ->label(__('general.site_setup'))
                            ->schema([
                                VerticalTabs\Tabs::make()
                                    ->tabs([
                                        VerticalTabs\Tab::make('Settings')
                                            ->label(__('general.settings'))
                                            ->icon('heroicon-o-cog')
                                            ->schema([
                                                Livewire::make(SetupSetting::class),
                                            ]),
                                        VerticalTabs\Tab::make('Navigation Menu')
                                            ->label(__('general.navigation_menu'))
                                            ->icon('heroicon-o-list-bullet')
                                            ->schema([
                                                Livewire::make(NavigationMenuSetting::class)
                                                    ->lazy(),
                                            ]),
                                        VerticalTabs\Tab::make('Languages')
                                            ->label(__('general.languages'))
                                            ->icon('heroicon-o-language')
                                            ->schema([
                                                Livewire::make(LanguageSetting::class),
                                            ]),
                                        VerticalTabs\Tab::make('Featured')
                                            ->icon('heroicon-o-bookmark')
                                            ->schema([
                                                Livewire::make(FeaturedScheduledConferenceTable::class),
                                            ]),
                                    ]),
                            ]),
                        Tabs\Tab::make('Appearance')
                            ->label(__('general.appearance'))
                            ->schema([
                                VerticalTabs\Tabs::make()
                                    ->tabs([
                                        VerticalTabs\Tab::make('Sidebar')
                                            ->label(__('general.sidebar'))
                                            ->icon('heroicon-o-view-columns')
                                            ->schema([
                                                Livewire::make(SidebarSetting::class)
                                                    ->lazy(),
                                            ]),
                                    ]),
                            ]),

                    ])
                    ->contained(false),
            ]);
    }
}
