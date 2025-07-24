<?php

namespace App\Panel\Conference\Pages;

use App\Infolists\Components\VerticalTabs as InfolistsVerticalTabs;
use App\Panel\Conference\Livewire\CitationSetting;
use App\Panel\Conference\Livewire\Forms\Conferences\DOIRegistration;
use App\Panel\Conference\Livewire\Forms\Conferences\DOISetup;
use App\Panel\Conference\Livewire\Forms\Conferences\SearchEngineSetting;
use App\Panel\Conference\Livewire\LicenseSetting;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class DistributionSetting extends Page implements HasForms, HasInfolists
{
    use InteractsWithForms, InteractsWithInfolists;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationIcon = 'heroicon-o-window';

    protected static string $view = 'panel.conference.pages.distribution';

    public static function getNavigationLabel(): string
    {
        return __('general.distribution');
    }

    public function getHeading(): string|Htmlable
    {
        return __('general.distribution_settings');
    }

    public static function getNavigationGroup(): string
    {
        return __('general.settings');
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
                Tabs::make('distribution_settings')
                    ->tabs([
                        Tabs\Tab::make('Papers')
                            ->label(__('general.papers'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                InfolistsVerticalTabs\Tabs::make()
                                    ->schema([
                                        InfolistsVerticalTabs\Tab::make('License')
                                            ->label('License')
                                            ->schema([
                                                Livewire::make(LicenseSetting::class),
                                            ]),
                                        InfolistsVerticalTabs\Tab::make('Citation')
                                            ->label(__('general.citation'))
                                            ->schema([
                                                Livewire::make(CitationSetting::class),
                                            ]),

                                    ]),
                            ]),
                        Tabs\Tab::make('DOI')
                            ->icon('academicon-doi')
                            ->schema([
                                InfolistsVerticalTabs\Tabs::make()
                                    ->schema([
                                        InfolistsVerticalTabs\Tab::make('Setup')
                                            ->label(__('general.setup'))
                                            ->schema([
                                                Livewire::make(DOISetup::class),
                                            ]),
                                        InfolistsVerticalTabs\Tab::make('Registration')
                                            ->label(__('general.registration'))
                                            ->schema([
                                                Livewire::make(DOIRegistration::class),
                                            ]),
                                    ]),
                            ]),
                        Tabs\Tab::make('Search Indexing')
                            ->label(__('general.search_indexing'))
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Livewire::make(SearchEngineSetting::class),
                            ]),
                    ])
                    ->contained(false),
            ]);
    }
}
