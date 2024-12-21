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

    protected static string $view = 'panel.conference.resources.submission-resource.pages.list-submission';

    protected const TAB_MYQUEUE = 'My Queue';

    protected const TAB_ACTIVE = 'Active';

    protected const TAB_UNASSIGNED = 'Unassigned';

    protected const TAB_PRESENTATION = 'Presentation';

    protected const TAB_ARCHIVED = 'Archived';

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

    public static function generateQueryByCurrentUser(string $tab)
    {
        $user = Auth::user();
        $query = static::getResource()::getEloquentQuery()
            ->withCount('editors');

        if ($user->hasAnyRole([
            UserRole::ScheduledConferenceEditor,
            UserRole::TrackEditor,
            UserRole::ConferenceManager,
            UserRole::Admin,
        ])) {
            return $query
                ->when($tab == static::TAB_MYQUEUE, fn($query) => $query->whereIn('status', [
                    SubmissionStatus::Queued,
                    SubmissionStatus::Incomplete,
                ]))
                ->when($tab == static::TAB_PRESENTATION, fn($query) => $query->whereIn('status', [
                    SubmissionStatus::OnPresentation,
                    SubmissionStatus::Editing,
                ]))
                ->when($tab === static::TAB_ARCHIVED, fn($query) => $query->whereIn('status', [
                    SubmissionStatus::Published,
                    SubmissionStatus::Declined,
                    SubmissionStatus::PaymentDeclined,
                    SubmissionStatus::Withdrawn,
                ]))
                ->when($tab === static::TAB_UNASSIGNED, fn($query) => $query->where('status', '!=', SubmissionStatus::Published)->where('user_id', '!=', auth()->id())->having('editors_count', 0))
                ->when($tab === static::TAB_ACTIVE, fn($query) => $query->having('editors_count', '>', 0)
                    ->whereIn('status', [
                        SubmissionStatus::OnReview,
                        SubmissionStatus::OnPayment,
                    ]));
        }

        if ($user->hasRole(UserRole::Reviewer)) {

            $query->orWhere(fn($query) => $query->whereHas('reviews', fn(Builder $query) => $query->where('user_id', Auth::id())))
                ->when($tab == static::TAB_PRESENTATION, fn($query) => $query->whereIn('status', [
                    SubmissionStatus::OnPresentation,
                    SubmissionStatus::Editing,
                ]))
                ->when($tab == static::TAB_MYQUEUE, fn($query) => $query->whereIn('status', [
                    SubmissionStatus::OnReview,
                    SubmissionStatus::Editing,
                    SubmissionStatus::OnPresentation,
                    SubmissionStatus::OnPayment,
                ]))
                ->when($tab == static::TAB_ARCHIVED, fn($query) => $query->whereIn('status', [
                    SubmissionStatus::Published,
                    SubmissionStatus::Declined,
                    SubmissionStatus::Withdrawn,
                    SubmissionStatus::PaymentDeclined,
                ]));
        }

        if ($user->hasAnyRole([UserRole::Author, UserRole::Reader]) || $user->roles->isEmpty()) {
            $query->orWhere(fn($query) => $query->where('user_id', $user->id)
                ->when($tab == static::TAB_PRESENTATION, fn($query) => $query->whereIn('status', [
                    SubmissionStatus::OnPresentation,
                    SubmissionStatus::Editing,
                ]))
                ->when($tab == static::TAB_MYQUEUE, fn($query) => $query->whereIn('status', [
                    SubmissionStatus::Queued,
                    SubmissionStatus::Incomplete,
                    SubmissionStatus::OnReview,
                    SubmissionStatus::Editing,
                    SubmissionStatus::OnPayment,
                ]))
                ->when($tab == static::TAB_ARCHIVED, fn($query) => $query->whereIn('status', [
                    SubmissionStatus::Published,
                    SubmissionStatus::Declined,
                    SubmissionStatus::Withdrawn,
                    SubmissionStatus::PaymentDeclined,
                ])));
        }

        return $query;
    }

    public function getTabs(): array
    {
        $tabs = [
            static::TAB_MYQUEUE => $this->tabMyQueue(),
        ];

        if (auth()->user()->can('submitAs', Submission::class)) {
            $tabs[static::TAB_UNASSIGNED] = $this->tabUnassigned();
            $tabs[static::TAB_ACTIVE] = $this->tabActive();
        }

        $tabs[static::TAB_ARCHIVED] = $this->tabArchived();

        return $tabs;
    }

    private function createTab(string $label, string $tabType): Tab
    {
        $query = static::generateQueryByCurrentUser($tabType);

        $count = $query->count();

        return Tab::make($label)
            ->modifyQueryUsing(fn(): Builder => $query)
            ->badge(fn(): int => $count)
            ->badgeColor(fn(): string => $count > 0 ? 'primary' : 'gray');
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
        $modifyQuery = fn(Builder $query) => $query->whereIn('status', [
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
