<?php

namespace App\Panel\Conference\Resources\PresenterResource\Pages;

use App\Models\Enums\PresenterStatus;
use App\Models\Presenter;
use App\Panel\Conference\Resources\PresenterResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;

class ManagePresenters extends ManageRecords
{
    protected static string $resource = PresenterResource::class;

    public function getTabs(): array
    {
        return [
            'unchecked' => Tab::make(__('translation.presenter.managePresentersTabUnchecked'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PresenterStatus::Unchecked))
                ->badge($this->presenterTabQuery(PresenterStatus::Unchecked)->count())
                ->badgeColor($this->presenterTabQuery(PresenterStatus::Unchecked)->count() > 0 ? 'primary' : 'gray'),
            'approved' => Tab::make(__('translation.presenter.managePresentersTabApproved'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PresenterStatus::Approve))
                ->badge($this->presenterTabQuery(PresenterStatus::Approve)->count())
                ->badgeColor($this->presenterTabQuery(PresenterStatus::Approve)->count() > 0 ? 'primary' : 'gray'),
            'rejected' => Tab::make(__('translation.presenter.managePresentersTabRejected'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PresenterStatus::Reject))
                ->badge($this->presenterTabQuery(PresenterStatus::Reject)->count())
                ->badgeColor($this->presenterTabQuery(PresenterStatus::Reject)->count() > 0 ? 'primary' : 'gray'),
        ];
    }

    public function presenterTabQuery(PresenterStatus $status): Builder
    {
        $presenterQuery = $this->getTableQuery();
        return $presenterQuery->whereStatus($status)->whereHas('submission', function ($query) {
            $query->where('conference_id', app()->getCurrentConference()->getKey());
        });
    }
}
