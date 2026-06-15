<?php

namespace App\Services\Notifications;

use App\Models\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

class OperationalNotificationRecipients
{
    /**
     * @param  array<int, UserRole|string>  $roles
     * @return Collection<int, User>
     */
    public function forRoles(array $roles): Collection
    {
        $roleNames = collect($roles)
            ->map(fn (UserRole|string $role): string => $role instanceof UserRole ? $role->value : $role)
            ->reject(fn (string $role): bool => $role === UserRole::Admin->value)
            ->unique()
            ->values();

        if ($roleNames->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roleNames))
            ->get()
            ->unique(fn (User $user): int|string => $user->getKey())
            ->values();
    }

    /**
     * @param  iterable<User>  $users
     * @return Collection<int, User>
     */
    public function uniqueUsers(iterable $users): Collection
    {
        return collect($users)
            ->filter(fn ($user): bool => $user instanceof User && filled($user->getKey()))
            ->unique(fn (User $user): int|string => $user->getKey())
            ->values();
    }
}
