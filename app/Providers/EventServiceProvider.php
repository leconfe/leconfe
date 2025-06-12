<?php

namespace App\Providers;

use App\Events\PluginInstalled;
use App\Listeners\LogSentEmail;
use App\Listeners\RegisterPluginVersion;
use App\Models\Conference;
use App\Models\ScheduledConference;
use App\Models\Site;
use App\Models\User;
use App\Observers\ConferenceObserver;
use App\Observers\ScheduledConferenceObserver;
use App\Observers\SiteObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Mail\Events\MessageSent;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        MessageSent::class => [
            LogSentEmail::class,
        ],
        PluginInstalled::class => [
            RegisterPluginVersion::class,
        ],
    ];

    protected $observers = [
        User::class => [UserObserver::class],
        ScheduledConference::class => [ScheduledConferenceObserver::class],
        Site::class => [SiteObserver::class],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
