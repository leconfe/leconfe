<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;

use App\Models\Enums\UserRole;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::before(function (Authorizable $user, string $ability) {
            if (! Str::contains($ability, ':')) {
                return null;
            }

            $permission = Permission::getPermission(['name' => $ability]);
            if (! $permission) {
                DB::transaction(function () use ($ability) {
                    Permission::create([
                        'name' => $ability,
                    ]);
                });
            }
        });

        Gate::after(function ($user, $ability) {
            return $user->hasRole(UserRole::Admin) ? true : null;
        });
    }
}
