<?php

namespace App\Http\Middleware;

use App\Models\Conference;
use App\Providers\PanelProvider;
use Closure;
use Filament\Facades\Filament;
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

        $conference = app()->getCurrentConference();

        if ($panel->getId() === PanelProvider::PANEL_CONFERENCE) {

            if ($user->can('view', $conference)) {
                return $next($request);
            }
            if ($conference->currentScheduledConference) {
                return redirect()->to($conference->currentScheduledConference->getPanelUrl());
            }

            abort(403);
        }

        return $next($request);
    }
}
