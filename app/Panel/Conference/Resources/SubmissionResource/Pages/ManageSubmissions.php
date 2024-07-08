<?php

namespace App\Panel\Conference\Resources\SubmissionResource\Pages;

use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Panel\Conference\Livewire\Workflows\Classes\StageManager;
use App\Panel\Conference\Pages\Settings\Workflow;
use App\Panel\Conference\Resources\SubmissionResource;
use Awcodes\Shout\Components\ShoutEntry;
use Filament\Actions\Action;
use Filament\Infolists\Infolist;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ManageSubmissions extends ManageRecords
{
    protected static string $resource = SubmissionResource::class;

    protected static string $view = 'panel.conference.resources.submission-resource.pages.list-submission';

    protected const TAB_MYQUEUE = 'My Queue';

    protected const TAB_ACTIVE = 'Active';

    protected const TAB_PUBLISHED = 'Published';

    protected const TAB_ARCHIVED = 'Archived';

    public static function getNavigationLabel(): string
    {
        return __('translation.submissions.labelSubmission');
    }
   
    public function getHeading(): string|Htmlable
    {
        return __('translation.submissions.labelSubmission');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                ShoutEntry::make('title')
                    ->hidden(function () {
                        return StageManager::callForAbstract()->isStageOpen() || ! Auth::user()->can('Workflow:update');
                    })
                    ->type('warning')
                    ->content(function () {
                        $htmlString =  __('translation.submissions.contentCallForAbstractStageIsClosed');;
                        $htmlString .= sprintf(
                            "<a href='%s' class='text-warning-700 hover:underline'>%s</a> %s",
                            Workflow::getUrl(),
                            __('translation.submissions.contentClickHere'),
                            __('translation.submissions.contentToOpenIt')
                        );
                        

                        return new HtmlString($htmlString);
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('Settings')
                ->button()
                ->authorize('Workflow:update')
                ->outlined()
                ->icon('heroicon-o-cog')
                ->url(Workflow::getUrl())
                ->label(__('translation.submissions.labelButtonSettings')),
            Action::make('create')
                ->button()
                ->authorize('Submission:create')
                ->disabled(
                    fn (): bool => ! StageManager::callForAbstract()->isStageOpen()
                )
                ->url(static::$resource::getUrl('create'))
                ->icon('heroicon-o-plus')
                ->label(function (Action $action) {
                    if ($action->isDisabled()) {
                        return __('translation.submissions.labelButtonSubmissionIsNotOpen');
                    }

                    return __('translation.submissions.labelButtonSubmissionn');
                }),
        ];
    }

    public static function generateQueryByCurrentUser(string $tabs)
    {
        $statuses = match ($tabs) {
            static::TAB_MYQUEUE => [
                SubmissionStatus::Queued,
            ],
            static::TAB_ACTIVE => [
                SubmissionStatus::OnReview,
                SubmissionStatus::Editing,
            ],
            static::TAB_PUBLISHED => [
                SubmissionStatus::Published,
            ],
            static::TAB_ARCHIVED => [
                SubmissionStatus::Declined,
                SubmissionStatus::Withdrawn,
            ],
            default => null,
        };

        $query = static::getResource()::getEloquentQuery();

        if (Auth::user()->hasAnyRole([
            UserRole::Admin->value,
            UserRole::ConferenceManager->value,
        ])) {
            return $query->whereIn('status', $statuses)->when(
                $tabs == static::TAB_MYQUEUE,
                function (Builder $query) {
                    $query->orWhere([
                        ['user_id', '=', Auth::id()],
                        ['status', '=', SubmissionStatus::Incomplete],
                    ]);
                }
            );
        }

        // Digunakan untuk menentukan mengetahui kondisi sebelumnya sudah ada atau belum
        $conditionBeforeExist = false;

        return $query->when(
            Auth::user()->hasRole(UserRole::Author->value),
            function (Builder $query) use ($statuses, &$conditionBeforeExist) {
                $query->where('user_id', Auth::id())->whereIn('status', $statuses);
                $conditionBeforeExist = true;
            }
        )->when(
            Auth::user()->hasRole(UserRole::Reviewer->value),
            function (Builder $query) use (&$conditionBeforeExist, $tabs, $statuses) {
                $query->when(
                    $conditionBeforeExist,
                    function (Builder $query) {
                        $query->orWhereHas('reviews', function (Builder $query) {
                            return $query->where('user_id', Auth::id());
                        });
                    },
                    function (Builder $query) {
                        $query->whereHas('reviews', function (Builder $query) {
                            return $query->where('user_id', Auth::id());
                        });
                    }
                )->when($tabs != static::TAB_MYQUEUE, function (Builder $query) use ($statuses) {
                    $query->whereIn('status', $statuses);
                });
                $conditionBeforeExist = true;
            }
        )->when(
            Auth::user()->hasRole(UserRole::Editor->value),
            function (Builder $query) use ($statuses, &$conditionBeforeExist) {
                $query->when($conditionBeforeExist, function (Builder $query) {
                    $query->orWhereHas('participants', function (Builder $query) {
                        return $query->where('user_id', Auth::id());
                    });
                }, function (Builder $query) {
                    $query->whereHas('participants', function (Builder $query) {
                        return $query->where('user_id', Auth::id());
                    });
                })->whereIn('status', $statuses);
            }
        )->when(
            $tabs == static::TAB_MYQUEUE,
            function (Builder $query) {
                $query->orWhere([
                    ['user_id', '=', Auth::id()],
                    ['status', '=', SubmissionStatus::Incomplete],
                ]);
            }
        );
    }

    /** Need to be optimized */
    public function getTabs(): array
    {
        return [
            static::TAB_MYQUEUE => $this->createTab(__('translation.submissions.tabMyQueue'), static::TAB_MYQUEUE),
            static::TAB_ACTIVE => $this->createTab(__('translation.submissions.tabActive'), static::TAB_ACTIVE),
            static::TAB_PUBLISHED => $this->createTab(__('translation.submissions.tabPublished'), static::TAB_PUBLISHED),
            static::TAB_ARCHIVED => $this->createTab(__('translation.submissions.tabArchived'), static::TAB_ARCHIVED),
        ];
    }

    private function createTab(string $label, string $tabType): Tab
    {
        $query = static::generateQueryByCurrentUser($tabType);
        $count = $query->count();

        return Tab::make($label)
            ->modifyQueryUsing(fn (): Builder => $query)
            ->badge(fn (): int => $count)
            ->badgeColor(fn (): string => $count > 0 ? 'primary' : 'gray');
    }
}
