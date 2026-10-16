<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\Multilingual\ContentLocales;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\App;

/**
 * Switch the session to the preferred language of a user who logs in.
 */
class ApplyPreferredLocale
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! app(ContentLocales::class)->isEnabled($user->locale)) {
            return;
        }

        if (app()->bound('session')) {
            session()->put('locale', $user->locale);
        }

        App::setLocale($user->locale);
    }
}
