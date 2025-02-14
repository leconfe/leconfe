<?php

namespace App\Http\Middleware;

use App\Models\ScheduledConference;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToScheduledConference
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isInstalled()) {
            return $next($request);
        }

        $conference = app()->getCurrentConference();

        if (! $conference || ! $conference->getMeta('scheduled_conference_redirect')) {
            return $next($request);
        }

        $scheduledConference = ScheduledConference::find($conference->getMeta('scheduled_conference_redirect'));

        if (! $scheduledConference) {
            return $next($request);
        }

        return redirect()->to($scheduledConference->getUrl());
    }
}
