<?php

namespace App\Panel\Series\Resources\AnnouncementResource\Pages;


use App\Actions\Announcements\AnnouncementCreateAction;
use App\Models\Enums\ContentType;
use App\Panel\Series\Resources\AnnouncementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnnouncements extends ListRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->using(fn (array $data) => AnnouncementCreateAction::run($data, $data['send_email'] ?? false)),
        ];
    }
}
