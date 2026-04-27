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

    public array $formData = [];

    public function mount(): void
    {
        $this->form->fill([
            'roles' => [],
        ]);
    }

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

    protected function buildRoleCards(array $availableRoles, array $availableRoleDescriptions): array
    {
        $styleMap = [
            'Author' => [
                'icon' => 'pencil-square',
                'checkedRingClass' => 'peer-checked:border-primary-500 peer-checked:bg-primary-50/50 dark:peer-checked:border-primary-500 dark:peer-checked:bg-primary-900/20',
                'iconClass' => 'text-primary-600 dark:text-primary-400',
                'titleClass' => 'group-hover:text-primary-600 dark:group-hover:text-primary-400',
                'checkClass' => 'peer-checked:border-primary-500 peer-checked:bg-primary-500',
            ],
            'Reviewer' => [
                'icon' => 'clipboard-document-check',
                'checkedRingClass' => 'peer-checked:border-warning-500 peer-checked:bg-warning-50/50 dark:peer-checked:border-warning-500 dark:peer-checked:bg-warning-900/20',
                'iconClass' => 'text-warning-600 dark:text-warning-400',
                'titleClass' => 'group-hover:text-warning-600 dark:group-hover:text-warning-400',
                'checkClass' => 'peer-checked:border-warning-500 peer-checked:bg-warning-500',
            ],
            'Participant' => [
                'icon' => 'users',
                'checkedRingClass' => 'peer-checked:border-success-500 peer-checked:bg-success-50/50 dark:peer-checked:border-success-500 dark:peer-checked:bg-success-900/20',
                'iconClass' => 'text-success-600 dark:text-success-400',
                'titleClass' => 'group-hover:text-success-600 dark:group-hover:text-success-400',
                'checkClass' => 'peer-checked:border-success-500 peer-checked:bg-success-500',
            ],
        ];

        $defaultStyle = [
            'icon' => 'user',
            'checkedRingClass' => 'peer-checked:border-gray-500 peer-checked:bg-gray-100 dark:peer-checked:border-gray-400 dark:peer-checked:bg-gray-700/40',
            'iconClass' => 'text-gray-600 dark:text-gray-300',
            'titleClass' => 'group-hover:text-gray-900 dark:group-hover:text-gray-100',
            'checkClass' => 'peer-checked:border-gray-500 peer-checked:bg-gray-500',
        ];

        return collect($availableRoles)
            ->map(function (string $roleName) use ($styleMap, $defaultStyle, $availableRoleDescriptions): array {
                $style = $styleMap[$roleName] ?? $defaultStyle;

                return [
                    'name' => $roleName,
                    'description' => $availableRoleDescriptions[$roleName] ?? 'Select this role to continue with your conference activities.',
                    'icon' => $style['icon'],
                    'checkedRingClass' => $style['checkedRingClass'],
                    'iconClass' => $style['iconClass'],
                    'titleClass' => $style['titleClass'],
                    'checkClass' => $style['checkClass'],
                ];
            })
            ->values()
            ->toArray();
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
