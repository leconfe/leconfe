<?php

namespace App\Panel\Conference\Resources\UserResource\Pages;

use App\Models\UserInvitation;
use App\Panel\Conference\Resources\UserResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;
    protected static string $view = 'panel.conference.resources.user-resource.pages.list-users';

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getInvitationPendingCountProperty(): int
    {
        $conferenceId = app()->getCurrentConferenceId();
        $scheduledConferenceId = app()->getCurrentScheduledConferenceId();

        return UserInvitation::query()
            ->when($scheduledConferenceId, fn (Builder $query) => $query->where('scheduled_conference_id', $scheduledConferenceId))
            ->when(! $scheduledConferenceId && $conferenceId, fn (Builder $query) => $query
                ->where('conference_id', $conferenceId)
                ->whereNull('scheduled_conference_id'))
            ->where('status', 'pending')
            ->count();
    }
}
