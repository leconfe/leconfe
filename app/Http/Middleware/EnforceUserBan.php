<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceUserBan
{
    public function handle(Request $request, Closure $next): Response
    {
        // A scoped ban must not prevent the user from explicitly signing out.
        if (! $request->routeIs('logout', 'livewirePageGroup.*.pages.logout', 'filament.*.auth.logout')) {
            $this->check($request);
        }

        return $next($request);
    }

    public function check(Request $request): void
    {
        $ban = $request->user()?->activeBanInCurrentContext();

        if (! $ban) {
            return;
        }

        if ($ban->scope()->isGlobal()) {
            auth()->logout();
            $session = $request->hasSession() ? $request->session() : session();
            $session->invalidate();
            $session->regenerateToken();
        }

        abort(403, $ban->scheduled_conference_id
            ? __('ban.scheduled_conference_restricted')
            : ($ban->conference_id ? __('ban.conference_restricted') : __('ban.global_restricted')));
    }
}
