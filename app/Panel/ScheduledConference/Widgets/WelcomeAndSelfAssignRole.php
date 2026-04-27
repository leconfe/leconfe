<?php

namespace App\Panel\ScheduledConference\Widgets;

use App\Models\Enums\UserRole;
use App\Panel\ScheduledConference\Pages\ParticipantRegistration;
use App\Panel\ScheduledConference\Pages\PaymentDetail;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class WelcomeAndSelfAssignRole extends Widget
{
    protected static string $view = 'panel.scheduledConference.widgets.welcome-and-self-assign-role';

    protected int|string|array $columnSpan = 'full';

    public array $formData = [
        'roles' => [],
    ];

    public static function canView(): bool
    {
        $user = auth()->user();
        $scheduledConferenceId = app()->getCurrentScheduledConferenceId();

        if (!$user || !$scheduledConferenceId) {
            return false;
        }

        return $user->roles->isEmpty() || $user->cannot('update', app()->getCurrentScheduledConference());
    }

    protected function getViewData(): array
    {
        $availableRoles = UserRole::getAllowedSelfAssignRoleNames();
        $availableRoleDescriptions = UserRole::getAllowedSelfAssignRoleDescriptions();
        $user = auth()->user();

        return [
            'isAssignRole' => !$user->roles()->exists() && !$user->roles()->withoutGlobalScopes()->exists(),
            'scheduledConference' => app()->getCurrentScheduledConference(),
            'submissionUrl' => SubmissionResource::getUrl(),
            'participantRegistrationUrl' => ParticipantRegistration::getUrl(),
            'participantPaymentUrl' => PaymentDetail::getUrl(),
            'roleCards' => $this->buildRoleCards($availableRoles, $availableRoleDescriptions),
        ];
    }

    protected function buildRoleCards(array $roles, array $descriptions): array
    {
        $colorMap = [
            'Author' => 'primary',
            'Reviewer' => 'warning',
            'Participant' => 'success',
        ];

        return collect($roles)
            ->map(function ($role) use ($colorMap, $descriptions) {
                return [
                    'name' => $role,
                    'description' => $descriptions[$role]
                        ?? 'Select this role to continue with your conference activities.',
                    'icon' => $this->getRoleIcon($role),
                    'color' => $colorMap[$role] ?? 'gray',
                ];
            })
            ->values()
            ->toArray();
    }

    protected function getRoleIcon(string $role): string
    {
        return match ($role) {
            'Author' => 'pencil-square',
            'Reviewer' => 'clipboard-document-check',
            'Participant' => 'users',
            default => 'user',
        };
    }

    public function submitRoles(): void
    {
        $allowedRoles = array_values(UserRole::getAllowedSelfAssignRoleNames());

        $selfAssignRoles = collect($this->formData['roles'] ?? [])
            ->filter(fn($role) => is_string($role) && in_array($role, $allowedRoles, true))
            ->unique()
            ->values()
            ->toArray();

        if (empty($selfAssignRoles)) {
            Notification::make()
                ->warning()
                ->title(__('general.no_roles_selected'))
                ->send();

            return;
        }

        $user = auth()->user();

        if (!$user) {
            Notification::make()
                ->error()
                ->title(__('general.user_not_found'))
                ->send();

            return;
        }

        $user->assignRole($selfAssignRoles);

        Notification::make()
            ->success()
            ->title(__('general.roles_assigned_successfully'))
            ->send();
    }
}
