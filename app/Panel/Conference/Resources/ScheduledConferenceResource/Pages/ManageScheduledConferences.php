<?php

namespace App\Panel\Conference\Resources\ScheduledConferenceResource\Pages;

use App\Actions\ScheduledConferences\ScheduledConferenceCreateAction;
use App\Panel\Conference\Resources\ScheduledConferenceResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;

class ManageScheduledConferences extends ManageRecords
{
    protected static string $resource = ScheduledConferenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->modalWidth(MaxWidth::ExtraLarge)
                ->using(fn (array $data) => ScheduledConferenceCreateAction::run($data)),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make()
                ->label("All")
                ->badge(fn () => ScheduledConferenceResource::getEloquentQuery()->count()),
            'trash' => Tab::make()
                ->label(__('general.trash'))
                ->badge(fn () => ScheduledConferenceResource::getEloquentQuery()->onlyTrashed()->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->onlyTrashed()),
        ];
    }
}
