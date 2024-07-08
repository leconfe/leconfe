<?php

namespace App\Panel\Administration\Pages;

use Filament\Facades\Filament;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action;
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

    // protected static ?string $title = 'Administration';

    public static function getNavigationLabel(): string
    {
        return __('translation.dashboard.getNavigationLabel');
    }
   
    public function getHeading(): string|Htmlable
    {
        return __('translation.dashboard.getNavigationLabel');
    }
        
    
    protected static string $view = 'panel.administration.pages.dashboard';

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('')
                    ->schema([
                        Actions::make([
                            Action::make(__('translation.dashboard.titleSystemInformation'))
                                ->icon('heroicon-m-cog-8-tooth')
                                ->color('primary')
                                ->requiresConfirmation()
                                ->outlined()
                                ->extraAttributes(['class' => 'w-48'])
                                ->url(route('phpmyinfo')),

                        ]),
                        Actions::make([
                            Action::make(__('translation.dashboard.titleExpireUserSession'))
                                ->icon('heroicon-m-user')
                                ->color('primary')
                                ->requiresConfirmation()
                                ->outlined()
                                ->successNotification(
                                    Notification::make()
                                        ->success()
                                        ->title(__('translation.dashboard.successNotificationtitle'))
                                        ->body(__('translation.dashboard.successNotificationbodyUserSession')),
                                )
                                ->extraAttributes(['class' => 'w-48'])
                                ->action(fn (Action $action) => $this->expireUserSession($action)),

                        ]),
                        Actions::make([
                            Action::make(__('translation.dashboard.titleClearDataCaches'))
                                ->icon('heroicon-m-circle-stack')
                                ->color('primary')
                                ->requiresConfirmation()
                                ->outlined()
                                ->successNotification(
                                    Notification::make()
                                        ->success()
                                        ->title(__('translation.dashboard.successNotificationbodyClearSuccesfully'))
                                        ->body(__('translation.dashboard.failureNotificationTitleClearSuccesfully')),
                                )
                                ->extraAttributes(['class' => 'w-48'])
                                ->action(function (Action $action) {
                                    $this->runArtisanCommand('cache:clear', $action);
                                    $this->runArtisanCommand('optimize:clear', $action);
                                }),
                        ]),
                        Actions::make([
                            Action::make(__('translation.dashboard.titleClearViewCaches'))
                                ->icon('heroicon-m-trash')
                                ->color('primary')
                                ->requiresConfirmation()
                                ->outlined()
                                ->successNotification(
                                    Notification::make()
                                        ->success()
                                        ->title(__('translation.dashboard.successNotificationbodyClearSuccesfullys'))
                                        ->body(__('translation.dashboard.successNotificationbodyViewCaches')),
                                )
                                ->extraAttributes(['class' => 'w-48'])
                                ->action(function (Action $action) {
                                    $this->runArtisanCommand('view:clear', $action);
                                    $this->runArtisanCommand('icons:clear', $action);
                                    $this->runArtisanCommand('icons:cache', $action);
                                }),
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
