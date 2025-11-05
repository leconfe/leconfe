<?php

namespace App\Http\Middleware;

use App\Models\Enums\ScheduledConferenceState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class IdentifyScheduledConference
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $scheduledConference = app()->getCurrentScheduledConference();
        if (! $scheduledConference) {
            return abort(404);
        }
        
        if(Gate::allows('view', $scheduledConference)){
            return $next($request);
        }

        return abort(404);
    }
}
