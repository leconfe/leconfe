<?php

namespace App\Panel\Conference\Resources\ProceedingResource\Pages;

use App\Models\Proceeding;
use App\Panel\Conference\Resources\ProceedingResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;

class ManageProceedings extends ManageRecords
{
    protected static string $resource = ProceedingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->modalWidth(MaxWidth::ExtraLarge),
        ];
    }

    public function getTabs(): array
    {
        return [
            'future' => Tab::make()
                ->label(__('translation.proceeding.manageProceedingsTabsTitleFuture'))
                ->modifyQueryUsing(fn (Builder $query) => $query->published(false))
                ->badge(fn () => Proceeding::published(false)->count())
                ->badgeColor(fn () => Proceeding::published(false)->count() ? 'primary' : 'gray'),
            'back' => Tab::make()
                ->label(__('translation.proceeding.manageProceedingsTabsTitleBack'))
                ->modifyQueryUsing(fn (Builder $query) => $query->published())
                ->badge(fn () => Proceeding::published()->count())
                ->badgeColor(fn () => Proceeding::published()->count() ? 'primary' : 'gray'),
        ];
    }
}
