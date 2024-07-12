<?php

namespace App\Providers;

use App\Facades\Plugin;
use App\Facades\Setting;
use App\Http\Middleware\IdentifyConference;
use App\Http\Middleware\IdentifySeries;
use App\Http\Middleware\MustVerifyEmail;
use App\Http\Middleware\PanelAuthenticate;
use App\Panel\Conference\Pages\Dashboard;
use App\Panel\Conference\Pages\Profile;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TimePicker;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Support\Facades\Config;

class PanelProvider extends ServiceProvider
{
    public function seriesPanel(Panel $panel): Panel
    {
        $currentConference = app()->getCurrentConference();
        $locales = Config::get('app.locales');
        $selectedLanguages = $selectedLanguages ?? [];
        if ($currentConference) {
            $selectedLanguages = $currentConference->getMeta('languages');
        }


        $this->setupPanel($panel)
            ->id('series')
            ->path('{conference:path}/series/{serie:path}/panel')
            ->bootUsing(fn () => static::setupFilamentComponent())
            ->homeUrl(fn () => app()->getCurrentSerie()?->getHomeUrl())
            ->discoverResources(in: app_path('Panel/Series/Resources'), for: 'App\\Panel\\Series\\Resources')
            ->discoverPages(in: app_path('Panel/Series/Pages'), for: 'App\\Panel\\Series\\Pages')
            ->discoverWidgets(in: app_path('Panel/Series/Widgets'), for: 'App\\Panel\\Series\\Widgets')
            ->discoverLivewireComponents(in: app_path('Panel/Series/Livewire'), for: 'App\\Panel\\Series\\Livewire')
            ->userMenuItems([
                'logout' => MenuItem::make()
                    ->url(fn (): string => route('conference.logout')),
                    ...collect($locales)->map(function ($label, $locale) use ($selectedLanguages) {
                        if (in_array($locale, $selectedLanguages ?? [])) {
                            return MenuItem::make()
                                ->label($label)
                                ->url(fn (): string => route('translation', $locale));
                        }
                        return null;
                    })->filter()->toArray(),
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn () => view('panel.series.hooks.topbar'),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn () => view('panel.series.hooks.sidebar-nav-start'),
            )
            ->middleware([
                IdentifyConference::class,
                IdentifySeries::class,
                ...static::getMiddleware(),
            ], true)
            ->authMiddleware(static::getAuthMiddleware(), true);

        return $panel;
    }

    public function conferencePanel(Panel $panel): Panel
    {
        $currentConference = app()->getCurrentConference();
        $locales = Config::get('app.locales');
        $selectedLanguages = $selectedLanguages ?? [];
        if ($currentConference) {
            $selectedLanguages = $currentConference->getMeta('languages');
        }

        $this->setupPanel($panel)
            ->id('conference')
            ->default()
            ->path('{conference:path}/panel')
            ->bootUsing(fn () => static::setupFilamentComponent())
            ->homeUrl(fn () => route('livewirePageGroup.conference.pages.home', ['conference' => $currentConference]))
            ->discoverResources(in: app_path('Panel/Conference/Resources'), for: 'App\\Panel\\Conference\\Resources')
            ->discoverPages(in: app_path('Panel/Conference/Pages'), for: 'App\\Panel\\Conference\\Pages')
            ->discoverWidgets(in: app_path('Panel/Conference/Widgets'), for: 'App\\Panel\\Conference\\Widgets')
            ->discoverLivewireComponents(in: app_path('Panel/Conference/Livewire'), for: 'App\\Panel\\Conference\\Livewire')
            ->pages(static::getPages())
            ->userMenuItems([
                'logout' => MenuItem::make()
                    ->url(fn (): string => route('conference.logout')),
                ...collect($locales)->map(function ($label, $locale) use ($selectedLanguages) {
                    if (in_array($locale, $selectedLanguages ?? [])) {
                        return MenuItem::make()
                            ->label($label)
                            ->url(fn (): string => route('translation', $locale));
                    }
                    return null;
                })->filter()->toArray(),
                'profile' => MenuItem::make()
                    ->url(fn (): string => Profile::getUrl()),
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn () => view('panel.conference.hooks.topbar'),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn () => view('panel.conference.hooks.sidebar-nav-start'),
            )
            ->middleware([
                IdentifyConference::class,
                ...static::getMiddleware(),
            ], true)
            ->authMiddleware(static::getAuthMiddleware(), true);

        Plugin::getPlugins()->each(function ($plugin) use ($panel) {
            $plugin->onConferencePanel($panel);
        });

        return $panel;
    }

    public function administrationPanel(Panel $panel): Panel
    {
        $this->setupPanel($panel)
            ->id('administration')
            ->path('administration')
            ->homeUrl(fn () => route('livewirePageGroup.website.pages.home'))
            ->bootUsing(function () {
                static::setupFilamentComponent();
            })
            ->discoverResources(in: app_path('Panel/Administration/Resources'), for: 'App\\Panel\\Administration\\Resources')
            ->discoverPages(in: app_path('Panel/Administration/Pages'), for: 'App\\Panel\\Administration\\Pages')
            ->discoverWidgets(in: app_path('Panel/Administration/Widgets'), for: 'App\\Panel\\Administration\\Widgets')
            ->discoverLivewireComponents(in: app_path('Panel/Administration/Livewire'), for: 'App\\Panel\\Administration\\Livewire')
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn () => view('panel.administration.hooks.sidebar-nav-start'),
            )
            ->userMenuItems([
                'logout' => MenuItem::make()
                    ->url(fn (): string => route('logout')),
                'profile' => MenuItem::make()
                    ->url(fn (): string => Profile::getUrl(panel: 'administration')),
            ])
            ->middleware(static::getMiddleware(), true)
            ->authMiddleware(static::getAuthMiddleware(), true);

        Plugin::getPlugins()->each(function ($plugin) use ($panel) {
            $plugin->onAdministrationPanel($panel);
        });

        return $panel;
    }

    public function setupPanel(Panel $panel): Panel
    {
        return $panel
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->renderHook(
                'panels::scripts.before',
                fn () => Blade::render(<<<'Blade'
                        @vite(['resources/panel/js/panel.js'])
                    Blade)
            )
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->viteTheme('resources/panel/css/panel.css')
            ->colors([
                'primary' => Color::hex('#1c3569'),
            ])
            ->darkMode(false)
            ->databaseNotifications()
            ->databaseNotificationsPolling(null);
    }

    public function register(): void
    {
        Filament::registerPanel(
            fn (): Panel => $this->seriesPanel(Panel::make()),
        );

        Filament::registerPanel(
            fn (): Panel => $this->conferencePanel(Panel::make()),
        );

        Filament::registerPanel(
            fn (): Panel => $this->administrationPanel(Panel::make()),
        );

        // $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::anonymousComponentPath(resource_path('views/panel/conference/components'), 'panel');
        Blade::anonymousComponentPath(resource_path('views/panel/administration/components'), 'administration');
        Blade::anonymousComponentPath(resource_path('views/panel/series/components'), 'series');
    }

    public static function getPages(): array
    {
        return [
            Dashboard::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [];
    }

    public static function getMiddleware(): array
    {
        return [
            'web',
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
            'logout.banned',
        ];
    }

    public static function getAuthMiddleware(): array
    {
        return [
            PanelAuthenticate::class,
            MustVerifyEmail::class,
        ];
    }

    public static function setupFilamentComponent()
    {
        // TODO Validasi file type menggunakan format extension, bukan dengan mime type,
        // hal ini agar mempermudah pengguna dalam melakukan setting file apa saja yang diperbolehkan
        // Saat ini SpatieMediaLibraryFileUpload hanya support file validation dengan mime type.
        // Solusi mungkin buat custom component upload dengan menggunakan library seperti dropzone, atau yang lainnya.
        SpatieMediaLibraryFileUpload::configureUsing(function (SpatieMediaLibraryFileUpload $fileUpload): void {
            $fileUpload
                ->imageResizeTargetWidth(2048)
                ->imageResizeTargetWidth(2048)
                ->imageResizeMode('contain')
                ->imageResizeUpscale(false)
                ->maxSize(config('media-library.max_file_size') / 1024);

            // ->acceptedFileTypes(config('media-library.accepted_file_types'))
        });
        DatePicker::configureUsing(function (DatePicker $datePicker): void {
            $datePicker
                ->native(false)
                ->displayFormat(Setting::get('format_date'));
        });

        TimePicker::configureUsing(function (TimePicker $timePicker): void {
            $timePicker->displayFormat(Setting::get('format_time'));
        });

        Table::configureUsing(function (Table $table): void {
            $table
                ->defaultPaginationPageOption(5)
                ->paginationPageOptions([5, 10, 25, 50]);
        });

        TinyEditor::configureUsing(function (TinyEditor $tinyEditor): void {
            $tinyEditor
                ->setRelativeUrls(false)
                ->setRemoveScriptHost(false)
                ->toolbarSticky(false);
        });
    }
}
