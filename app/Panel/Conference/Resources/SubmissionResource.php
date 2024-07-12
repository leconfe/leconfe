<?php

namespace App\Panel\Conference\Resources;

use App\Constants\ReviewerStatus;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Submission;
use App\Panel\Conference\Resources\SubmissionResource\Pages;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SubmissionResource extends Resource
{
    protected static ?int $navigationSort = 1;

    protected static ?string $model = Submission::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->getMeta('title');
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['user', 'participants', 'reviews']);
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record?->getMeta('title') ?? static::getModelLabel();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['meta', 'user'])->orderBy('updated_at', 'desc');
    }

    public static function getModelLabel(): string
    {
        return __('translation.submissions.labelSubmission');
    }

    public static function getGlobalSearchResults(string $search): Collection
    {
        $search = strtolower($search);

        $query = static::getGlobalSearchEloquentQuery();

        $isFirst = true;
        foreach (explode(' ', $search) as $searchWord) {
            $whereHas = $isFirst ? 'whereHas' : 'orWhereHas';

            $query->{$whereHas}('meta', function (Builder $q) use ($searchWord) {
                $q->whereIn('key', static::getGloballySearchableAttributes())
                    ->where('value', 'like', "%{$searchWord}%");
            });

            $isFirst = false;
        }

        return $query
            ->limit(static::getGlobalSearchResultsLimit())
            ->get()
            ->map(function (Model $record): ?GlobalSearchResult {
                $url = static::getGlobalSearchResultUrl($record);
                if (blank($url)) {
                    return null;
                }

                return new GlobalSearchResult(
                    title: static::getGlobalSearchResultTitle($record),
                    url: $url,
                    details: static::getGlobalSearchResultDetails($record),
                    actions: static::getGlobalSearchResultActions($record),
                );
            })
            ->filter();
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Author' => $record->user->name,
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'description'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(function (Submission $record) {
                $participantReviewer = $record->reviews()->where('user_id', auth()->id())->first();

                if ($participantReviewer) {
                    if ($participantReviewer->needConfirmation() || $participantReviewer->status == ReviewerStatus::DECLINED) {
                        return static::getUrl('reviewer-invitation', [
                            'record' => $record->id,
                        ]);
                    } else {
                        return static::getUrl('review', [
                            'record' => $record->id,
                        ]);
                    }
                }

                return static::getUrl('view', [
                    'record' => $record->id,
                    // 'stage' => '-'.str($record->stage->value)->slug('-').'-tab',
                ]);
            })
            ->columns([
                Split::make([
                    Stack::make([
                        Tables\Columns\TextColumn::make('title')
                            ->getStateUsing(fn (Submission $record) => $record->getMeta('title'))
                            ->description(function (Submission $record) {
                                return $record->user->fullName;
                            })
                            ->searchable(query: function (Builder $query, string $search): Builder {
                                return $query
                                    ->whereMeta('title', 'like', "%{$search}%");
                            }),
                        Tables\Columns\TextColumn::make('status')
                            ->extraAttributes([
                                'class' => 'mt-2',
                            ])
                            ->badge()
                            ->formatStateUsing(
                                fn (Submission $record) => $record->status
                            ),

                    ]),
                    Stack::make([
                        Tables\Columns\TextColumn::make('editor-assigned-badges')
                            ->badge()
                            ->extraAttributes([
                                'class' => 'mt-2',
                            ])
                            ->color('warning')
                            ->getStateUsing(function (Submission $record) {
                                $editorAssigned = $record->participants()
                                    ->whereHas(
                                        'role',
                                        fn (Builder $query) => $query->where('name', UserRole::Editor)
                                    )
                                    ->count();

                                if (! $editorAssigned && $record->stage != SubmissionStage::Wizard) {
                                    return __('translation.submissions.submissionResourceNoEditorAssigned');
                                }
                            }),
                        Tables\Columns\TextColumn::make('withdrawn-notification')
                            ->badge()
                            ->extraAttributes([
                                'class' => 'mt-2',
                            ])
                            ->color('danger')
                            ->getStateUsing(function (Submission $record) {
                                if (filled($record->withdrawn_reason)) {
                                    return __('translation.submissions.submissionResourcePendingWithdrawal');
                                }
                            }),
                    ]),
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label(__('translation.button.view'))
                    ->icon('lineawesome-eye-solid')
                    ->authorize(function (Submission $record) {
                        return auth()->user()->can('view', $record);
                    })
                    ->url(fn (Submission $record) => static::getUrl('view', [
                        'record' => $record->id,
                    ])),
                Tables\Actions\DeleteAction::make()
                    ->authorize(
                        fn (Submission $record) => auth()->user()->can('delete', $record)
                    ),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(
                        SubmissionStatus::array()
                    )
                    ->searchable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSubmissions::route('/'),
            'create' => Pages\CreateSubmission::route('/create'),
            'complete' => Pages\CompleteSubmission::route('/complete/{record}'),
            'view' => Pages\ViewSubmission::route('/{record}'),
            'review' => Pages\ReviewSubmissionPage::route('/{record}/review'),
            'reviewer-invitation' => Pages\ReviewerInvitationPage::route('/{record}/reviewer-invitation'),
        ];
    }
}
