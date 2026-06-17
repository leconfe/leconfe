<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Constants\ReviewerStatus;
use App\Models\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReviewResult extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-eye';

    protected static string $view = 'panel.scheduledConference.pages.review-result';

    protected static ?int $navigationSort = 99;

    public function mount(): void
    {
        $this->authorize('update', App::getCurrentScheduledConference());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()->can('update', App::getCurrentScheduledConference());
    }

    public static function reviewResultsQuery(): Builder
    {
        return Submission::query()
            ->with(['meta'])
            ->whereIn('status', [
                SubmissionStatus::OnReview,
                SubmissionStatus::OnPresentation,
                SubmissionStatus::Editing,
                SubmissionStatus::Published,
            ])
            ->whereExists(static::effectiveReviewsSubquery()
                ->selectRaw('1')
                ->where('effective_reviews.status', ReviewerStatus::ACCEPTED)
                ->whereNotNull('effective_reviews.date_completed'))
            ->selectSub(static::effectiveReviewsSubquery()
                ->selectRaw('count(*)'), 'effective_reviews_count')
            ->selectSub(static::effectiveReviewsSubquery()
                ->selectRaw('count(*)')
                ->where('effective_reviews.status', ReviewerStatus::ACCEPTED)
                ->whereNotNull('effective_reviews.date_completed'), 'effective_completed_reviews_count')
            ->selectSub(static::effectiveReviewsSubquery()
                ->selectRaw('avg(effective_reviews.score)')
                ->where('effective_reviews.status', ReviewerStatus::ACCEPTED)
                ->whereNotNull('effective_reviews.date_completed'), 'effective_reviews_avg_score');
    }

    protected static function effectiveReviewsSubquery(): QueryBuilder
    {
        return DB::table('reviews as effective_reviews')
            ->join('submission_review_rounds as effective_rounds', 'effective_rounds.id', '=', 'effective_reviews.review_round_id')
            ->whereColumn('effective_reviews.submission_id', 'submissions.id')
            ->whereIn('effective_reviews.status', [
                ReviewerStatus::PENDING,
                ReviewerStatus::ACCEPTED,
            ])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('reviews as newer_reviews')
                    ->join('submission_review_rounds as newer_rounds', 'newer_rounds.id', '=', 'newer_reviews.review_round_id')
                    ->whereColumn('newer_reviews.submission_id', 'effective_reviews.submission_id')
                    ->whereColumn('newer_reviews.user_id', 'effective_reviews.user_id')
                    ->where(function ($query) {
                        $query->whereColumn('newer_rounds.round_number', '>', 'effective_rounds.round_number')
                            ->orWhere(function ($query) {
                                $query->whereColumn('newer_rounds.round_number', 'effective_rounds.round_number')
                                    ->whereColumn('newer_reviews.id', '>', 'effective_reviews.id');
                            });
                    });
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::reviewResultsQuery())
            ->defaultSort('effective_reviews_avg_score', 'desc')
            ->columns([
                TextColumn::make('effective_reviews_avg_score')
                    ->extraCellAttributes([
                        'style' => 'width: 1px',
                    ])
                    ->sortable()
                    ->label('Score')
                    ->searchable(query: fn ($query, $search) => $query->whereMeta('title', 'like', "%$search%"))
                    ->numeric(maxDecimalPlaces: 2),
                TextColumn::make('reviews')
                    ->extraCellAttributes([
                        'style' => 'width: 1px',
                    ])
                    ->getStateUsing(fn ($record) => $record->effective_completed_reviews_count.' / '.$record->effective_reviews_count),
                TextColumn::make('id')
                    ->label('ID')
                    ->extraCellAttributes([
                        'style' => 'width: 1px',
                    ])
                    ->grow(false),
                TextColumn::make('title')
                    ->getStateUsing(fn ($record) => $record->getMeta('title'))
                    ->color('primary')
                    ->openUrlInNewTab()
                    ->url(fn ($record) => SubmissionResource::getUrl('view', ['record' => $record]))
                    ->wrap(),
                TextColumn::make('status')
                    ->extraAttributes([
                        'class' => 'mt-2',
                    ])
                    ->badge()
                    ->formatStateUsing(
                        fn (Submission $record) => $record->status?->value
                    ),

            ])
            ->filters([
                // ...
            ])
            ->actions([
                // ...
            ])
            ->bulkActions([
                // ...
            ]);
    }
}
