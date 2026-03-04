<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MustVerifyEmail
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.must_verify_email')) {
            return $next($request);
        }

        if (! $request->user()) {
            return redirect()->to(app()->getLoginUrl());
        }

        if (! $request->user()->hasVerifiedEmail()) {
            if ($scheduledConference = app()->getCurrentScheduledConference()) {
                return redirect()->route('livewirePageGroup.scheduledConference.pages.email-verification', [
                    'conference' => $scheduledConference->conference->path,
                    'serie' => $scheduledConference->path,
                ]);
            }

            if ($conference = app()->getCurrentConference()) {
                return redirect()->route('livewirePageGroup.conference.pages.email-verification', [
                    'conference' => $conference->path,
                ]);
            }

            return redirect()->route('livewirePageGroup.website.pages.email-verification');
        }

        return $next($request);
    }
}
