<?php

namespace App\Panel\Series\Resources\TimelineResource\Pages;

use App\Panel\Series\Resources\TimelineResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTimeline extends ManageRecords
{
    protected static string $resource = TimelineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->modalHeading(__('translation.timeLineResource.timeLineResourceAddTimeline'))
                ->form(fn () => TimelineResource::formSchemas())
                ->mutateFormDataUsing(function (array $data) {
                    $dateFormat = date('Y-m-d', strtotime($data['date']));
                    $data['date'] = $dateFormat;

                    return $data;
                }),
        ];
    }
}
