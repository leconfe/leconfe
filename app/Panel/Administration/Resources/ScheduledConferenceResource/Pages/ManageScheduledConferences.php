<?php

namespace App\Panel\Administration\Resources\ScheduledConferenceResource\Pages;

use App\Actions\ScheduledConferences\ScheduledConferenceCreateAction;
use App\Models\Enums\ScheduledConferenceState;
use App\Panel\Administration\Resources\ScheduledConferenceResource;
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
            'current' => Tab::make()
                ->label(__('general.current'))
                ->badge(fn () => ScheduledConferenceResource::getEloquentQuery()->where('state', ScheduledConferenceState::Current)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('state', ScheduledConferenceState::Current)),
            'draft' => Tab::make()
                ->label(__('general.draft'))
                ->badge(fn () => ScheduledConferenceResource::getEloquentQuery()->where('state', ScheduledConferenceState::Draft)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('state', ScheduledConferenceState::Draft)),
            'upcoming' => Tab::make()
                ->label(__('general.upcoming'))
                ->badge(fn () => ScheduledConferenceResource::getEloquentQuery()->where('state', ScheduledConferenceState::Published)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('state', ScheduledConferenceState::Published)),
            'archived' => Tab::make()
                ->label(__('general.archived'))
                ->badge(fn () => ScheduledConferenceResource::getEloquentQuery()->where('state', ScheduledConferenceState::Archived)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('state', ScheduledConferenceState::Archived)),
            'trash' => Tab::make()
                ->label(__('general.trash'))
                ->badge(fn () => ScheduledConferenceResource::getEloquentQuery()->onlyTrashed()->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->onlyTrashed()),
        ];
    }
}
