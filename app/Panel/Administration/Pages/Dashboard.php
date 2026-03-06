<?php

namespace App\Panel\Administration\Pages;

use App\Actions\Leconfe\Relink;
use App\Facades\Setting;
use App\Models\Role;
use App\Models\ScheduledConference;
use App\Models\User;
use Carbon\Carbon;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Collection;

class Dashboard extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static ?string $navigationIcon = 'heroicon-m-home';

    protected static string $view = 'panel.administration.pages.dashboard';

    public static function getNavigationLabel(): string
    {
        return __('general.dashboard');
    }

    public function getHeading(): string|Htmlable
    {
        if (Auth::user()->can('Administration:view')) {
            return __('general.administration');
        }

        return __('general.dashboard');
    }

    public static function canAccess(): bool
    {
        return Auth::check();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('')
                    ->visible(fn (): bool => Auth::user()->can('Administration:view'))
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

    public function getScheduledConferencePortalsProperty(): Collection
    {
        $userId = Auth::id();

        $assignedRoles = DB::table('model_has_roles')
            ->select(['role_id', 'conference_id', 'scheduled_conference_id'])
            ->where('model_type', User::class)
            ->where('model_id', $userId)
            ->get();

        if ($assignedRoles->isEmpty()) {
            return collect();
        }

        $roles = Role::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $assignedRoles->pluck('role_id')->unique()->all())
            ->pluck('name', 'id');

        $directRolesByScheduledConference = $assignedRoles
            ->where('scheduled_conference_id', '>', 0)
            ->groupBy('scheduled_conference_id')
            ->map(fn (Collection $rolesPerScheduledConference): Collection => $rolesPerScheduledConference
                ->pluck('role_id')
                ->map(fn ($roleId) => $roles->get($roleId))
                ->filter()
                ->unique()
                ->sort()
                ->values());

        $conferenceRoles = $assignedRoles
            ->where('conference_id', '>', 0)
            ->where('scheduled_conference_id', 0)
            ->groupBy('conference_id')
            ->map(fn (Collection $rolesPerConference): Collection => $rolesPerConference
                ->pluck('role_id')
                ->map(fn ($roleId) => $roles->get($roleId))
                ->filter()
                ->unique()
                ->sort()
                ->values());

        if ($directRolesByScheduledConference->isEmpty() && $conferenceRoles->isEmpty()) {
            return collect();
        }

        $scheduledConferences = ScheduledConference::query()
            ->withoutGlobalScopes()
            ->select(['id', 'conference_id', 'title', 'path', 'date_start', 'date_end', 'state'])
            ->with(['conference:id,name,path'])
            ->where(function ($query) use ($directRolesByScheduledConference, $conferenceRoles) {
                if ($directRolesByScheduledConference->isNotEmpty()) {
                    $query->whereIn('id', $directRolesByScheduledConference->keys()->all());
                }

                if ($conferenceRoles->isNotEmpty()) {
                    $query->orWhereIn('conference_id', $conferenceRoles->keys()->all());
                }
            })
            ->orderByDesc('date_start')
            ->orderByDesc('id')
            ->get();

        return $scheduledConferences
            ->map(function (ScheduledConference $scheduledConference) use ($conferenceRoles, $directRolesByScheduledConference): array {
                $roleNames = $directRolesByScheduledConference
                    ->get($scheduledConference->getKey(), collect())
                    ->merge($conferenceRoles->get($scheduledConference->conference_id, collect()))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values();

                return [
                    'title' => $scheduledConference->title,
                    'conference_name' => $scheduledConference->conference?->name,
                    'date_range' => $this->formatDateRange($scheduledConference->date_start?->toDateString(), $scheduledConference->date_end?->toDateString()),
                    'roles' => $roleNames,
                    'panel_url' => route('filament.scheduledConference.pages.dashboard', [
                        'conference' => $scheduledConference->conference?->path,
                        'serie' => $scheduledConference->path,
                    ]),
                    'state' => $scheduledConference->state?->value ?? $scheduledConference->state,
                ];
            })
            ->values();
    }

    protected function formatDateRange(?string $startDate, ?string $endDate): ?string
    {
        if (! $startDate && ! $endDate) {
            return null;
        }

        $format = Setting::get('format_date');
        $startDateFormatted = $startDate ? Carbon::parse($startDate)->translatedFormat($format) : null;
        $endDateFormatted = $endDate ? Carbon::parse($endDate)->translatedFormat($format) : null;

        if ($startDateFormatted && $endDateFormatted) {
            return "{$startDateFormatted} - {$endDateFormatted}";
        }

        return $startDateFormatted ?? $endDateFormatted;
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
