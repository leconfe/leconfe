<?php

namespace App\Panel\ScheduledConference\Widgets;

use App\Models\Role;
use App\Models\Enums\UserRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RegistrationWarning extends Widget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string $view = 'panel.scheduledConference.widgets.registration-warning';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -100;

    public static function canView(): bool
    {
        $user = auth()->user();
        $scheduledConferenceId = app()->getCurrentScheduledConferenceId();

        if (!$user || !$scheduledConferenceId) {
            return false;
        }

        return !static::isRegisteredInCurrentScheduledConference($user->getKey(), $scheduledConferenceId);
    }

    public function assignRoleAction(): Action
    {
        return Action::make('assignRole')
            ->label('Assign')
            ->icon('heroicon-o-user-plus')
            ->color('warning')
            ->form([
                Select::make('roles')
                    ->label('Role')
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options($this->getAssignableRoleOptions()),
            ])
            ->modalSubmitActionLabel('Assign')
            ->action(function (array $data): void {
                $user = auth()->user();
                $roleIds = collect($data['roles'] ?? [])->filter()->map(fn($roleId) => (int) $roleId)->values();
                $scheduledConferenceId = app()->getCurrentScheduledConferenceId();
                $conferenceId = app()->getCurrentConferenceId() ?? app()->getCurrentScheduledConference()?->conference_id ?? 0;

                if (!$user || !$scheduledConferenceId || $roleIds->isEmpty()) {
                    return;
                }

                $availableRoleIds = Role::withoutGlobalScopes()
                    ->where('scheduled_conference_id', $scheduledConferenceId)
                    ->whereIn('id', $roleIds)
                    ->pluck('id');

                $attachPayload = $availableRoleIds
                    ->mapWithKeys(fn(int $roleId) => [
                        $roleId => [
                            'conference_id' => $conferenceId,
                            'scheduled_conference_id' => $scheduledConferenceId,
                        ]
                    ])
                    ->toArray();

                if (empty($attachPayload)) {
                    return;
                }

                $user->roles()->syncWithoutDetaching($attachPayload);
                $user->unsetRelation('roles');

                Notification::make()
                    ->success()
                    ->title('Role assigned successfully.')
                    ->send();

                $this->dispatch('$refresh');
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'scheduledConference' => app()->getCurrentScheduledConference(),
            'availableRoles' => $this->getAssignableRoleOptions(),
        ];
    }

    /**
     * @return Collection<array-key, string>
     */
    protected function getAssignableRoleOptions(): Collection
    {
        $scheduledConferenceId = app()->getCurrentScheduledConferenceId();
        $scheduledConference = app()->getCurrentScheduledConference();

        if (!$scheduledConferenceId || !$scheduledConference) {
            return collect();
        }

        $allowedSelfAssignRoles = collect($scheduledConference->getMeta('allowed_self_assign_roles') ?? [])
            ->filter()
            ->values();

        if ($allowedSelfAssignRoles->isEmpty()) {
            $allowedSelfAssignRoles = collect(UserRole::selfAssignedRoleNames())
                ->values();
        }

        return Role::withoutGlobalScopes()
            ->where('scheduled_conference_id', $scheduledConferenceId)
            ->whereIn('name', $allowedSelfAssignRoles)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    protected static function isRegisteredInCurrentScheduledConference(int $userId, int $scheduledConferenceId): bool
    {
        return DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
            ->where('model_type', User::class)
            ->where('model_id', $userId)
            ->where('scheduled_conference_id', $scheduledConferenceId)
            ->exists();
    }
}
