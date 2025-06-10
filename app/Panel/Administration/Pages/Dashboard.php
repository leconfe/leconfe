<?php

namespace App\Panel\Administration\Pages;

use App\Actions\Leconfe\Relink;
use Filament\Facades\Filament;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Dashboard extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static ?string $navigationIcon = 'heroicon-m-home';

    protected static string $view = 'panel.administration.pages.dashboard';
    
    protected static bool $shouldRegisterNavigation = false;


    public static function getNavigationLabel(): string
    {
        return __('general.dashboard');
    }

    public function getHeading(): string|Htmlable
    {
        return __('general.administration');
    }

    public static function canAccess(): bool
    {
        return Auth::user()->can('Administration:view');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('')
                    ->columns(2)
                    ->schema([
                        Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                Actions::make([
                                    Action::make(__('general.expire_user_session'))
                                        ->icon('heroicon-m-user')
                                        ->color('primary')
                                        ->requiresConfirmation()
                                        ->outlined()
                                        ->successNotification(
                                            Notification::make()
                                                ->success()
                                                ->title(__('general.session_cleared'))
                                                ->body(__('general.notification_successfully_cleared')),
                                        )
                                        ->extraAttributes(['class' => 'w-full'])
                                        ->action(fn (Action $action) => $this->expireUserSession($action)),
                                ]),
                                Actions::make([
                                    Action::make(__('general.clear_data_caches'))
                                        ->icon('heroicon-m-circle-stack')
                                        ->color('primary')
                                        ->requiresConfirmation()
                                        ->outlined()
                                        ->successNotification(
                                            Notification::make()
                                                ->success()
                                                ->title(__('general.successfully_cleared'))
                                                ->body(__('general.data_caches_cleared_successfully')),
                                        )
                                        ->extraAttributes(['class' => 'w-full'])
                                        ->action(function (Action $action) {
                                            $this->runArtisanCommand('cache:clear', $action);
                                            $this->runArtisanCommand('optimize:clear', $action);
                                        }),
                                ]),
                                Actions::make([
                                    Action::make(__('general.clear_view_caches'))
                                        ->icon('heroicon-m-trash')
                                        ->color('primary')
                                        ->requiresConfirmation()
                                        ->outlined()
                                        ->successNotification(
                                            Notification::make()
                                                ->success()
                                                ->title(__('general.successfully_cleared'))
                                                ->body(__('general.view_caches_cleared_successfully')),
                                        )
                                        ->extraAttributes(['class' => 'w-full'])
                                        ->action(function (Action $action) {
                                            $this->runArtisanCommand('view:clear', $action);
                                            $this->runArtisanCommand('icons:cache', $action);
                                        }),
                                ]),
                                Actions::make([
                                    Action::make('relink')
                                        ->label(__('general.fix_broken_public_storage_plugins'))
                                        ->icon('heroicon-m-folder')
                                        ->color('primary')
                                        ->requiresConfirmation()
                                        ->successNotificationTitle(__('general.successfully_relinked'))
                                        ->outlined()
                                        ->extraAttributes(['class' => 'w-full'])
                                        ->action(function (Action $action) {
                                            Relink::run();

                                            $action->sendSuccessNotification();
                                        }),
                                ]),
                                Actions::make([
                                    Action::make('system-information')
                                        ->label(__('general.system_information'))
                                        ->icon('heroicon-m-information-circle')
                                        ->color('primary')
                                        ->outlined()
                                        ->extraAttributes(['class' => 'w-full'])
                                        ->url(route(SystemInformation::getRouteName())),
                                ]),
                            ]),

                    ]),
            ]);
    }

    protected function expireUserSession(Action $action)
    {
        try {
            $userAuth = Filament::auth()->user();

            Session::flush();

            Auth::login($userAuth);

            session()->regenerate();

            $action->sendSuccessNotification();

            $this->redirect(Filament::getUrl());
        } catch (\Throwable $th) {
            $action->sendFailureNotification();
        }
    }

    protected function runArtisanCommand($command, Action $action)
    {
        try {
            Artisan::call($command);

            $action->sendSuccessNotification();
        } catch (\Throwable $th) {
            $action->sendFailureNotification();
        }
    }
}
