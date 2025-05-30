<?php

namespace App\Http\Middleware;

use App\Models\Conference;
use App\Models\Role;
use App\Providers\PanelProvider;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectPanelIfCannotAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        $panel = Filament::getCurrentPanel();

        if (! $panel) {
            abort(404);
        }

        // $conference = app()->getCurrentConference();
        $role = Role::query()
            ->withoutGlobalScopes()
            ->with([
                // 'conference',
                'scheduledConference' => fn (Builder $query) => $query->withoutGlobalScopes(),
            ])
            ->whereHas('users', fn ($query) => $query->where('id', $user->id))
            ->first();

        // if ($panel->getId() === PanelProvider::PANEL_CONFERENCE) {

        //     if ($user->can('view', $conference)) {
        //         return $next($request);
        //     }

        //     return redirect()->to($conference->currentScheduledConference->getPanelUrl());
        // }

        if ($panel->getId() === PanelProvider::PANEL_ADMINISTRATION) {
            if ($user->can('Administration:view')) {
                return $next($request);
            }

            if ($role?->scheduledConference) {
                return redirect()->to($role->scheduledConference->getPanelUrl());
            }

            if ($role?->conference) {
                return redirect()->to($role->conference->getPanelUrl());
            }

            return redirect()->to(Conference::first()->getPanelUrl());
        }

        return $next($request);
    }
}
