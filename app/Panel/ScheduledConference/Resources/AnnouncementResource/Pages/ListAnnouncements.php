<?php

namespace App\Panel\ScheduledConference\Resources\AnnouncementResource\Pages;

use App\Actions\Announcements\AnnouncementCreateAction;
use App\Models\Enums\UserRole;
use App\Models\User;
use App\Panel\ScheduledConference\Resources\AnnouncementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListAnnouncements extends ListRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->modalWidth(MaxWidth::TwoExtraLarge)
                ->using(fn (array $data) => AnnouncementCreateAction::run($data, $data['send_email'] ?? false)),
        ];
    }
}
