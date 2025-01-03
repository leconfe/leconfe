<?php

namespace App\Panel\ScheduledConference\Resources;

use App\Constants\ReviewerStatus;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SubmissionResource extends Resource
{
    protected static ?int $navigationSort = 1;

    protected static ?string $model = Submission::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record?->getMeta('title') ?? static::getModelLabel();
    }

    public static function getNavigationLabel(): string
    {
        return __('general.submissions');
    }

    public static function getModelLabel(): string
    {
        return __('general.submissions');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount([
                'editors',
                'reviews',
                'reviews as completed_reviews_count' => fn ($query) => $query->whereNotNull('date_completed'),
            ])
            ->with(['meta', 'user', 'reviews', 'participants'])
            ->orderBy('updated_at', 'desc');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(function (Submission $record) {
                $review = $record->reviews->where('user_id', auth()->id())->first();
                if ($review) {
                    if ($review->needConfirmation() || $review->status == ReviewerStatus::DECLINED) {
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
                    TextColumn::make('id')
                        ->grow(false)
                        ->searchable()
                        ->extraCellAttributes([
                            'style' => 'width: 1px',
                        ]),
                    Stack::make([
                        Tables\Columns\TextColumn::make('title')
                            ->getStateUsing(fn (Submission $record) => $record->getMeta('title'))
                            ->description(function (Submission $record) {
                                return $record->user->fullName;
                            })
                            ->searchable(query: function (Builder $query, string $search): Builder {
                                return $query
                                    ->whereMeta('title', 'like', "%{$search}%")
                                    ->orWhereHas('user', fn ($query) => $query->whereMeta('public_name', 'like', "%{$search}%")->orWhere('given_name', 'like', "%{$search}%")->orWhere('family_name', 'like', "%{$search}%"));
                            }),
                        Tables\Columns\TextColumn::make('status')
                            ->extraAttributes([
                                'class' => 'mt-2',
                            ])
                            ->badge()
                            ->formatStateUsing(
                                fn (Submission $record) => $record->status?->value
                            ),
                    ]),
                    Stack::make([
                        Tables\Columns\TextColumn::make('editor-assigned-badges')
                            ->badge()
                            ->extraAttributes([
                                'class' => 'mt-2',
                            ])
                            ->extraCellAttributes([
                                'style' => 'width: 1px',
                            ])
                            ->color('warning')
                            ->getStateUsing(function (Submission $record) {
                                $isEditorAssigned = $record->editors_count;

                                if (! $isEditorAssigned && $record->stage != SubmissionStage::Wizard) {
                                    return __('general.no_editor_assigned');
                                }
                            }),
                        Tables\Columns\TextColumn::make('reviews')
                            ->extraCellAttributes([
                                'style' => 'width: 1px',
                            ])
                            ->getStateUsing(fn ($record) => $record->reviews_count ? view('panel.scheduledConference.components.review-count', [
                                'reviews_count' => $record->reviews_count,
                                'completed_reviews_count' => $record->completed_reviews_count,
                            ]) : ''),
                        Tables\Columns\TextColumn::make('reviewed')
                            ->badge()
                            ->color('success')
                            ->getStateUsing(function (Submission $record) {
                                $review = $record->reviews->where('user_id', auth()->id())->first();
                                if (! $review) {
                                    return '';
                                }

                                if ($review->reviewSubmitted()) {
                                    return __('general.reviewed');
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
                                    return __('general.pending_withdrawal');
                                }
                            }),
                    ]),
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label(__('general.view'))
                    ->icon('lineawesome-eye-solid')
                    ->authorize(function (Submission $record) {
                        return auth()->user()->can('view', $record);
                    })
                    ->url(fn (Submission $record) => static::getUrl('view', [
                        'record' => $record->id,
                    ])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(
                        SubmissionStatus::array()
                    )
                    ->searchable(),
                SelectFilter::make('track')
                    ->relationship('track', 'title')
                    ->multiple()
                    ->searchable()
                    ->preload(),
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
