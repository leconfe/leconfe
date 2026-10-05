<?php

namespace App\Actions\Announcements;

use App\Mail\Templates\NewAnnouncementMail;
use App\Models\Announcement;
use App\Models\Enums\UserRole;
use App\Models\User;
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

        // Filter by users subscribed to announcement emails.
        $users = User::query()
            ->with('meta')
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
            ->whereMeta('enable_new_announcement_email', true)
            ->notBannedInScope(new \App\Support\UserBanScope($scheduledConference->conference_id, $scheduledConference->getKey()))
            ->lazy();

        foreach ($users as $user) {
            Mail::to($user->email)->send(new NewAnnouncementMail($announcement));
        }
    }

    public function asJob(Announcement $announcement): void
    {
        $this->handle($announcement);
    }
}
