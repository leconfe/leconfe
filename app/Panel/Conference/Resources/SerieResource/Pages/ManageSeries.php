<?php

namespace App\Panel\Conference\Resources\SerieResource\Pages;

use App\Actions\Series\SerieCreateAction;
use App\Models\Enums\SerieState;
use App\Panel\Conference\Resources\SerieResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Htmlable;

class ManageSeries extends ManageRecords
{
    protected static string $resource = SerieResource::class;  
    
    public function getHeading(): string|Htmlable
    {
        return  __('translation.serieSetting.serieSettingTitleLabel');
    }




    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->using(fn(array $data) => SerieCreateAction::run($data)),
        ];
    }

    public function getTabs(): array
    {
        return [
            'current' => Tab::make(__('translation.serie.managaSeriesTabCurrent'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('state', SerieState::Current)),
            'draft' => Tab::make(__('translation.serie.managaSeriesTabDraft'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('state', SerieState::Draft)),
            'upcoming' => Tab::make(__('translation.serie.managaSeriesTabUpcoming'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('state', SerieState::Published)),
            'archived' => Tab::make(__('translation.serie.managaSeriesTabArchived'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('state', SerieState::Archived)),
        ];
    }
}
