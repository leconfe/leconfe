<?php

namespace App\Panel\Conference\Pages\Settings;

use App\Infolists\Components\LivewireEntry;
use App\Infolists\Components\VerticalTabs as InfolistsVerticalTabs;
use App\Panel\Conference\Livewire\EmailSetting;
use App\Panel\Administration\Livewire\SidebarSetting;
use App\Panel\Conference\Livewire\AccessSetting;
use App\Panel\Conference\Livewire\DateAndTimeSetting;
use App\Panel\Conference\Livewire\Forms\Conferences\AdditionalInformationSetting;
use App\Panel\Conference\Livewire\Forms\Conferences\ContactSetting;
use App\Panel\Conference\Livewire\Forms\Conferences\InformationSetting;
use App\Panel\Conference\Livewire\Forms\Conferences\PrivacySetting;
use App\Panel\Conference\Livewire\Forms\Conferences\SelectLanguage;
use App\Panel\Conference\Livewire\Forms\Conferences\SetupSetting;
use App\Panel\Conference\Livewire\NavigationMenuSetting;
use Filament\Forms\Components\Livewire;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Support\Htmlable;

class ConferenceSetting extends Page implements HasForms, HasInfolists
{
    use InteractsWithForms, InteractsWithInfolists;

    protected static ?int $navigationSort = 1;

    // protected static ?string $navigationGroup = 'Settings';

    public static function getNavigationGroup(): string
    {
        return __('translation.pluginResource.navigationGroupTitle');
    }

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static string $view = 'panel.conference.pages.settings.conference';

    // protected ?string $heading = 'Conference Settings';

    // protected static ?string $navigationLabel = 'Conference';

    public static function getNavigationLabel(): string
    {
        return __('translation.conferenceSetting.getNavigationLabelConferenceSettings');
    }

    public function getHeading(): string|Htmlable
    {
        return __('translation.conferenceSetting.getNavigationLabelConferenceSettings');
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
                Tabs::make('website_settings')
                    ->tabs([
                        Tabs\Tab::make(__('translation.conferenceSettingsAbout.tabsHeadingAbout'))
                            ->schema([
                                InfolistsVerticalTabs\Tabs::make()
                                    ->schema([
                                        InfolistsVerticalTabs\Tab::make(__('translation.conferenceSettingsAbout.infolistsVerticalTabsInformation'))
                                            ->icon('heroicon-o-information-circle')
                                            ->schema([
                                                LivewireEntry::make('information-setting')
                                                    ->livewire(InformationSetting::class, [
                                                        'conference' => App::getCurrentConference(),
                                                    ]),
                                            ]),
                                        InfolistsVerticalTabs\Tab::make(__('translation.conferenceSettingsAbout.infolistsVerticalTabsAdditionalInformation'))
                                            ->icon('heroicon-o-plus-circle')
                                            ->schema([
                                                LivewireEntry::make('information-setting')
                                                    ->livewire(AdditionalInformationSetting::class, [
                                                        'conference' =>  App::getCurrentConference(),
                                                    ])
                                            ]),
                                        InfolistsVerticalTabs\Tab::make(__('translation.conferenceSettingsAbout.infolistsVerticalTabsPrivacy'))
                                            ->icon('heroicon-o-shield-check')
                                            ->schema([
                                                LivewireEntry::make('information-setting')
                                                    ->livewire(PrivacySetting::class, [
                                                        'conference' => App::getCurrentConference(),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tabs\Tab::make(__('translation.conferenceSettingsApprance.tabsHeadingAppearance'))
                            ->schema([
                                InfolistsVerticalTabs\Tabs::make()
                                    ->schema([
                                        InfolistsVerticalTabs\Tab::make(__('translation.conferenceSettingsApprance.infolistsVerticalTabsSetup'))
                                            ->icon('heroicon-o-adjustments-horizontal')
                                            ->schema([
                                                LivewireEntry::make('setup-setting')
                                                    ->livewire(SetupSetting::class, [
                                                        'conference' => App::getCurrentConference(),
                                                    ]),
                                            ]),
                                        InfolistsVerticalTabs\Tab::make(__('translation.conferenceSettingsApprance.infolistsVerticalTabsSidebar'))
                                            ->icon('heroicon-o-view-columns')
                                            ->schema([
                                                LivewireEntry::make('sidebar-setting')
                                                    ->livewire(SidebarSetting::class, [
                                                        'conference' => App::getCurrentConference(),
                                                    ]),
                                            ]),
                                        InfolistsVerticalTabs\Tab::make(__('translation.selectLanguage.pilihBahasa'))
                                            ->icon('heroicon-o-language')
                                            ->schema([
                                                LivewireEntry::make('select-langguage')
                                                    ->livewire(SelectLanguage::class, [
                                                        'conference' => App::getCurrentConference(),
                                                    ]),
                                            ]),
                                        InfolistsVerticalTabs\Tab::make(__('translation.conferenceSettingsApprance.infolistsVerticalTabsNavigationMenu'))
                                            ->icon('heroicon-o-list-bullet')
                                            ->schema([
                                                LivewireEntry::make('navigation-menu-setting')
                                                    ->livewire(NavigationMenuSetting::class),
                                            ]),
                                    ]),
                            ]),
                        Tabs\Tab::make(__('translation.conferenceSidebarSetting.notificationTitle'))
                            ->schema([
                                InfolistsVerticalTabs\Tabs::make()
                                    ->tabs([
                                        InfolistsVerticalTabs\Tab::make(__('translation.conferenceSidebarSetting.SetupSettingLabelAccessOptions'))
                                            ->icon('heroicon-o-information-circle')
                                            ->schema([
                                                LivewireEntry::make('access_setting')
                                                    ->livewire(AccessSetting::class)
                                                    ->lazy(),
                                            ]),
                                        InfolistsVerticalTabs\Tab::make(__('translation.conferenceSidebarSetting.SetupSettingLabelDateNTime'))
                                            ->icon('heroicon-o-clock')
                                            ->schema([
                                                LivewireEntry::make('date_and_time')
                                                    ->livewire(DateAndTimeSetting::class)
                                                    ->lazy(),
                                            ]),
                                    ]),
                            ]),
                        Tabs\Tab::make(__('translation.conferenceSidebarSetting.SetupSettingLabelEmail'))
                            ->schema([
                                LivewireEntry::make('mail_setting')
                                    ->livewire(EmailSetting::class)
                                    ->lazy(),
                            ]),
                    ])
                    ->contained(false),
            ]);
    }
}
