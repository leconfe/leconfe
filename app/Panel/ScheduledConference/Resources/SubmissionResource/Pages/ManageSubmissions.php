<?php

namespace App\Panel\ScheduledConference\Resources\SubmissionResource\Pages;

use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Review;
use App\Models\Submission;
use App\Models\SubmissionParticipant;
use App\Models\Timeline;
use App\Panel\ScheduledConference\Pages\WorkflowSetting;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Actions\Action;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ManageSubmissions extends ManageRecords
{
    protected static string $resource = SubmissionResource::class;

    // protected static string $view = 'panel.conference.resources.submission-resource.pages.list-submission';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('Settings')
                ->label(__('general.settings'))
                ->button()
                ->authorize('update', app()->getCurrentScheduledConference())
                ->outlined()
                ->icon('heroicon-o-cog')
                ->url(WorkflowSetting::getUrl()),
            Action::make('create')
                ->label(__('general.create'))
                ->button()
                ->disabled(
                    fn(): bool => ! Timeline::isSubmissionOpen()
                )
                ->url(static::$resource::getUrl('create'))
                ->icon('heroicon-o-plus')
                ->label(function (Action $action) {
                    if ($action->isDisabled()) {
                        return __('general.submission_is_not_open');
                    }

                    return __('general.submissions');
                }),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            $this->tabMyQueue(),
        ];

        if (auth()->user()->can('submitAs', Submission::class)) {
            $tabs[] = $this->tabUnassigned();
            $tabs[] = $this->tabActive();
        }

        $tabs[] = $this->tabArchived();

        return $tabs;
    }

    protected function tabMyQueue(): Tab
    {
        $modifyQuery = fn(Builder $query) => $query
            ->whereHas('participants', fn(Builder $query) => $query->where('user_id', auth()->id()))
            ->orWhereHas('reviews', fn(Builder $query) => $query->where('user_id', auth()->id()));

        return Tab::make(__('general.my_queue'))
            ->modifyQueryUsing($modifyQuery)
            ->badge(fn() => $modifyQuery(static::getResource()::getEloquentQuery())->count());
    }

    protected function tabUnassigned(): Tab
    {
        $modifyQuery = fn(Builder $query) => $query->doesntHave('editors');

        return Tab::make(__('general.unassigned'))
            ->modifyQueryUsing($modifyQuery)
            ->badge(fn() => $modifyQuery(static::getResource()::getEloquentQuery())->count());
    }
    
    protected function tabActive(): Tab
    {
        $modifyQuery = fn(Builder $query) => $query->has('editors')->whereIn('status', [
            SubmissionStatus::Queued,
            SubmissionStatus::OnReview,
            SubmissionStatus::OnPayment,
            SubmissionStatus::OnPresentation,
            SubmissionStatus::Editing,
        ]);

        return Tab::make(__('general.active'))
            ->modifyQueryUsing($modifyQuery)
            ->badge(fn() => $modifyQuery(static::getResource()::getEloquentQuery())->count());
    }

    protected function tabArchived(): Tab
    {
        $modifyQuery = fn(Builder $query) => $query->whereIn('status', [
            SubmissionStatus::Published,
            SubmissionStatus::Withdrawn,
            SubmissionStatus::Declined,
            SubmissionStatus::PaymentDeclined,
        ]);

        return Tab::make(__('general.archived'))
            ->modifyQueryUsing($modifyQuery)
            ->badge(fn() => $modifyQuery(static::getResource()::getEloquentQuery())->count());
    }
}
