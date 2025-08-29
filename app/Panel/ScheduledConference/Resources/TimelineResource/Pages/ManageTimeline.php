<?php

namespace App\Panel\ScheduledConference\Resources\TimelineResource\Pages;

use App\Actions\Timeline\TimelineCreateAction;
use App\Models\Timeline;
use App\Panel\ScheduledConference\Resources\TimelineResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ManageTimeline extends ListRecords
{
    protected static string $resource = TimelineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->modalHeading(__('general.add_timeline'))
                ->modalWidth(MaxWidth::ExtraLarge)
                ->model(Timeline::class)
                ->using(fn (array $data) => TimelineCreateAction::run($data))
                ->authorize('create', Timeline::class),
        ];
    }
}
