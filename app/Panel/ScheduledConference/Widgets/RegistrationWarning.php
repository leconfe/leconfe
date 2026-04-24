<?php

namespace App\Panel\ScheduledConference\Widgets;

use App\Models\Role;
use App\Models\Enums\UserRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

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

        // ensure user is from another scheduled conference and does not have any roles in this scheduled conference
        return $user->roles()->withoutGlobalScopes()->exists() && !$user->roles()->exists();
    }

    public function assignRoleAction(): Action
    {
        return Action::make('assignRole')
            ->label('Assign Roles')
            ->icon('heroicon-o-user-plus')
            ->modalWidth(MaxWidth::Large)
            ->color('warning')
            ->modalAutofocus(false)
            ->form([
                CheckboxList::make('roles')
                    ->hiddenLabel()
                    ->required()
                    ->options($this->getAssignableRoleOptions()),
            ])
            ->modalSubmitActionLabel('Assign')
            ->action(function (array $data): void {
                $user = auth()->user();
                $roleNames = collect($data['roles'] ?? [])->filter()->values();

                if (!$user || $roleNames->isEmpty()) {
                    return;
                }

                $roleNames->each(fn(string $roleName) => $user->assignRole($roleName));

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
        $scheduledConference = app()->getCurrentScheduledConference();

        if (!$scheduledConference) {
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
            ->availableRolesByContext()
            ->whereIn('name', $allowedSelfAssignRoles)
            ->orderBy('name')
            ->pluck('name', 'name');
    }
}
