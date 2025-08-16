<?php

namespace App\Actions\Announcements;

use App\Mail\Templates\NewAnnouncementMail;
use App\Models\Announcement;
use App\Models\Enums\UserRole;
use App\Models\ScheduledConference;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Lorisleiva\Actions\Concerns\AsAction;

class AnnouncementBroadcastMail
{
    use AsAction;

    public function handle(Announcement $announcement)
    {
        // Filter by user subsribe to announcement
        $users = User::query()
            ->with('meta')
            ->whereHas('roles', fn ($query) => $query->where('name', '!=', UserRole::Admin))
            ->whereMeta('enable_new_announcement_email', true)
            ->notBanned()
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
