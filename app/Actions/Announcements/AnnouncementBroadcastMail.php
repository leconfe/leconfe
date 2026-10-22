<?php

namespace App\Actions\Announcements;

use App\Mail\Templates\NewAnnouncementMail;
use App\Models\Announcement;
use App\Models\Enums\UserRole;
use App\Models\User;
use App\Services\EmailPreferences\Categories;
use App\Services\EmailPreferences\Preferences;
use Illuminate\Support\Facades\Mail;
use Lorisleiva\Actions\Concerns\AsAction;

class AnnouncementBroadcastMail
{
    use AsAction;

    public function handle(Announcement $announcement)
    {
        $modelHasRolesTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        $scheduledConference = $announcement->scheduledConference()
            ->withoutGlobalScopes()
            ->firstOrFail();

        $users = User::query()
            ->whereHas('roles', fn ($query) => $query
                ->withoutGlobalScopes()
                ->where('roles.conference_id', $scheduledConference->conference_id)
                ->where(function ($query) use ($scheduledConference) {
                    $query->where('roles.scheduled_conference_id', $scheduledConference->getKey())
                        ->orWhere(function ($query) {
                            $query->where('roles.name', UserRole::ConferenceManager->value)
                                ->where('roles.scheduled_conference_id', 0);
                        });
                })
                ->whereColumn('roles.conference_id', "{$modelHasRolesTable}.conference_id")
                ->whereColumn('roles.scheduled_conference_id', "{$modelHasRolesTable}.scheduled_conference_id"))
            ->whereDoesntHave('roles', fn ($query) => $query
                ->withoutGlobalScopes()
                ->where('roles.name', UserRole::Admin->value))
            ->notBannedInScope(new \App\Support\UserBanScope($scheduledConference->conference_id, $scheduledConference->getKey()))
            ->lazy();

        $preferences = app(Preferences::class);

        foreach ($users as $user) {
            // Only users subscribed to announcements for this scheduled conference.
            if (! $preferences->enabled($user, $scheduledConference->conference_id, $scheduledConference->getKey(), Categories::ANNOUNCEMENT)) {
                continue;
            }

            Mail::to($user->email)->send(new NewAnnouncementMail($announcement));
        }
    }

    public function asJob(Announcement $announcement): void
    {
        $this->handle($announcement);
    }
}
