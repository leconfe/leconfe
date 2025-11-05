<?php

namespace App\Providers;

use Filament\Panel;
use Livewire\Livewire;
use App\Facades\Plugin;
use App\Facades\Setting;
use App\Models\Conference;
use Filament\Tables\Table;
use GuzzleHttp\Psr7\MimeType;
use App\Models\Enums\UserRole;
use Filament\Facades\Filament;
use Filament\Navigation\MenuItem;
use Filament\Support\Colors\Color;
use App\Models\ScheduledConference;
use Filament\View\PanelsRenderHook;
use App\Forms\Components\TinyEditor;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Http\Middleware\MustVerifyEmail;
use App\Panel\Conference\Pages\Dashboard;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TimePicker;
use App\Http\Middleware\PanelAuthenticate;
use App\Http\Middleware\IdentifyConference;
use App\Http\Middleware\IdentifyScheduledConference;
use App\Panel\Administration\Pages\Profile;
use Filament\Support\Facades\FilamentColor;
use App\Http\Middleware\RedirectPanelIfCannotAccess;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;

class PanelProvider extends ServiceProvider
{
    public const PANEL_ADMINISTRATION = 'administration';

    public const PANEL_CONFERENCE = 'conference';

    public const PANEL_SCHEDULED_CONFERENCE = 'scheduledConference';

    public function scheduledConferencePanel(Panel $panel): Panel
    {
        $this->setupPanel($panel)
            ->id(static::PANEL_SCHEDULED_CONFERENCE)
            ->path('{conference:path}/scheduled/{serie:path}/panel')
            ->homeUrl(fn() => app()->getCurrentScheduledConference()?->getHomeUrl())
            ->discoverResources(in: app_path('Panel/ScheduledConference/Resources'), for: 'App\\Panel\\ScheduledConference\\Resources')
            ->discoverPages(in: app_path('Panel/ScheduledConference/Pages'), for: 'App\\Panel\\ScheduledConference\\Pages')
            ->discoverWidgets(in: app_path('Panel/ScheduledConference/Widgets'), for: 'App\\Panel\\ScheduledConference\\Widgets')
            ->discoverLivewireComponents(in: app_path('Panel/ScheduledConference/Livewire'), for: 'App\\Panel\\ScheduledConference\\Livewire')
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn() => view('panel.scheduledConference.hooks.topbar'),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                function () {
                    $currentConference = app()->getCurrentConference();
                    $currentScheduledConference = app()->getCurrentScheduledConference();
                    $scheduledConferences = ScheduledConference::query()
                        ->where('path', '!=', $currentScheduledConference->path)
                        ->with(['media'])
                        ->latest()
                        ->get();

                    return view('panel.scheduledConference.hooks.sidebar-nav-start', compact('currentConference', 'currentScheduledConference', 'scheduledConferences'));
                }
            )
            ->middleware([
                ...static::getMiddleware(),
                IdentifyScheduledConference::class,
            ], true)
            ->authMiddleware(static::getAuthMiddleware(), true);

        Plugin::getPlugins()->each(function ($plugin) use ($panel) {
            $plugin->onPanel($panel);
        });

        return $panel;
    }

    public function conferencePanel(Panel $panel): Panel
    {
        $this->setupPanel($panel)
            ->id(static::PANEL_CONFERENCE)
            ->default()
            ->path('{conference:path}/panel')
            ->homeUrl(fn() => route('livewirePageGroup.conference.pages.home', ['conference' => app()->getCurrentConference()]))
            ->discoverResources(in: app_path('Panel/Conference/Resources'), for: 'App\\Panel\\Conference\\Resources')
            ->discoverPages(in: app_path('Panel/Conference/Pages'), for: 'App\\Panel\\Conference\\Pages')
            ->discoverWidgets(in: app_path('Panel/Conference/Widgets'), for: 'App\\Panel\\Conference\\Widgets')
            ->discoverLivewireComponents(in: app_path('Panel/Conference/Livewire'), for: 'App\\Panel\\Conference\\Livewire')
            ->pages([
                Dashboard::class,
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn() => view('panel.conference.hooks.topbar'),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                function () {
                    $currentConference = app()->getCurrentConference();
                    $conferenceQuery = Conference::query()
                        ->when(! auth()->user()->hasRole(UserRole::Admin), fn($query) => $query->whereHas('conferenceUsers', function ($query) {
                            $query->where('model_has_roles.model_id', auth()->id());
                        }))
                        ->where('path', '!=', $currentConference->path)
                        ->with(['media'])
                        ->latest();

                    return view('panel.conference.hooks.sidebar-nav-start', [
                        'conferences' => $conferenceQuery->get(),
                    ]);
                }
            )
            ->middleware([
                IdentifyConference::class,
                ...static::getMiddleware(),
            ], true)
            ->authMiddleware(static::getAuthMiddleware(), true);

        Plugin::getPlugins()->each(function ($plugin) use ($panel) {
            $plugin->onPanel($panel);
        });

        return $panel;
    }

    public function administrationPanel(Panel $panel): Panel
    {
        $this->setupPanel($panel)
            ->id(static::PANEL_ADMINISTRATION)
            ->path('administration')
            ->homeUrl(fn() => route('livewirePageGroup.website.pages.home'))
            ->discoverResources(in: app_path('Panel/Administration/Resources'), for: 'App\\Panel\\Administration\\Resources')
            ->discoverPages(in: app_path('Panel/Administration/Pages'), for: 'App\\Panel\\Administration\\Pages')
            ->discoverWidgets(in: app_path('Panel/Administration/Widgets'), for: 'App\\Panel\\Administration\\Widgets')
            ->discoverLivewireComponents(in: app_path('Panel/Administration/Livewire'), for: 'App\\Panel\\Administration\\Livewire')
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn() => view('panel.administration.hooks.sidebar-nav-start'),
            )
            ->middleware(static::getMiddleware(), true)
            ->authMiddleware(static::getAuthMiddleware(), true);

        Plugin::getPlugins()->each(function ($plugin) use ($panel) {
            $plugin->onPanel($panel);
        });

        return $panel;
    }

    public function setupPanel(Panel $panel): Panel
    {
        return $panel
            ->favicon(asset('favicon.ico'))
            ->maxContentWidth('full')
            ->when(app()->isProduction(), fn(Panel $panel) => $panel->renderHook(
                PanelsRenderHook::FOOTER,
                fn() => Blade::render('<x-livewire-handle-error />')
            ))
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn() => Blade::render(<<<'Blade'
                        <x-footer-platform-panel />
                    Blade)
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_BEFORE,
                fn() => Blade::render(<<<'Blade'
                        @vite(['resources/panel/js/panel.js'])
                    Blade)
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_AFTER,
                function () {
                    $languages = Setting::get('languages', ['en']);
                    if (count($languages) < 2) {
                        return;
                    }

                    return Blade::render('@livewire(App\Livewire\LanguageSwitcher::class)');
                },
            )
            ->viteTheme('resources/panel/css/panel.css')
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->url(fn(): string => Profile::getUrl()),
            ])
            ->darkMode(false)
            ->databaseNotifications()
            ->databaseNotificationsPolling(null);
    }

    public function register(): void
    {
        Filament::registerPanel(
            fn(): Panel => $this->scheduledConferencePanel(Panel::make()),
        );

        Filament::registerPanel(
            fn(): Panel => $this->conferencePanel(Panel::make()),
        );

        Filament::registerPanel(
            fn(): Panel => $this->administrationPanel(Panel::make()),
        );

        FilamentColor::register([
            'primary' => Color::hex('#1c3569'),
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::anonymousComponentPath(resource_path('views/panel/conference/components'), 'panel');
        Blade::anonymousComponentPath(resource_path('views/panel/administration/components'), 'administration');
        Blade::anonymousComponentPath(resource_path('views/panel/scheduledConference/components'), 'scheduledConference');

        Livewire::setScriptRoute(function ($handle) {
            return Route::get(request()->getBaseUrl() . '/livewire/livewire.js', $handle);
        });

        static::setupFilamentComponent();
    }

    public static function getMiddleware(): array
    {
        return [
            'web',
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
        ];
    }

    public static function getAuthMiddleware(): array
    {
        return [
            PanelAuthenticate::class,
            MustVerifyEmail::class,
            'logout.banned',
            RedirectPanelIfCannotAccess::class,
        ];
    }

    public static function setupFilamentComponent()
    {
        FileUpload::configureUsing(fn(FileUpload $fileUpload) => static::configureFileUpload($fileUpload));

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
                ->defaultPaginationPageOption(10)
                ->paginationPageOptions([5, 10, 25, 50]);
            Table::$defaultDateDisplayFormat = Setting::get('format_date');
        });

        TinyEditor::configureUsing(function (TinyEditor $tinyEditor): void {
            $tinyEditor
                ->setRelativeUrls(false)
                ->setRemoveScriptHost(false)
                ->toolbarSticky(false);
        });
    }

    protected static function configureFileUpload(FileUpload $fileUpload): FileUpload
    {
        return $fileUpload
            ->imageResizeTargetWidth(2048)
            ->imageResizeTargetWidth(2048)
            ->imageResizeMode('contain')
            ->imageResizeUpscale(false)
            ->maxSize(config('media-library.max_file_size') / 1024)
            ->acceptedFileTypes(collect(config('media-library.accepted_file_types'))
                ->map(fn($ext) => MimeType::fromExtension($ext) ?? $ext)
                ->toArray());
    }
}
