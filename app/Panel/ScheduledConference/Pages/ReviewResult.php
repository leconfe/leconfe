<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Models\Author;
use App\Models\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;

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

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->requiresConfirmation()
                ->action(function () {
                    $name = implode('-', [
                        'review-result',
                        app()->getCurrentScheduledConference()->getKey(),
                        now()->timestamp,
                    ]);
                    $filename = Storage::disk('private-files')->path(auth()->user()->id . $name . '.xlsx');

                    $columns = [
                        "ID",
                        "Reviews",
                        "Score",
                        "Title",
                        "Authors",
                        "Submitter Name",
                        "Submitter Email",
                    ];

                    $writer = new \OpenSpout\Writer\XLSX\Writer();
                    $writer->openToFile($filename);

                    $writer->addRow(Row::fromValues($columns));

                    Submission::query()
                        ->with([
                            'meta',
                            'participants',
                            'authors' => fn($query) => $query->ordered(),
                            'editors.user',
                            'user',
                            'topics',
                        ])
                        ->whereIn('status', [
                            SubmissionStatus::OnReview,
                            SubmissionStatus::OnPresentation,
                            SubmissionStatus::Editing,
                            SubmissionStatus::Published,
                        ])
                        ->withAvg(['reviews' => fn($query) => $query->whereNotNull('date_completed')], 'score')
                        ->withCount([
                            'reviews',
                            'reviews as completed_reviews_count' => fn($query) => $query->whereNotNull('date_completed'),
                        ])
                        ->orderBy('reviews_avg_score', 'desc')
                        ->lazy()
                        ->each(fn(Submission $submission) => $writer->addRow(Row::fromValues([
                            $submission->getKey(),
                            $submission->completed_reviews_count . ' / ' . $submission->reviews_count,
                            number_format($submission->reviews_avg_score, 2),
                            $submission->getMeta('title'),
                            $submission->authors->implode(fn(Author $author) => Str::squish($author->given_name . ' ' . $author->family_name), ', '),
                            Str::squish($submission->user->given_name . ' ' . $submission->user->family_name),
                            $submission->user->email
                        ])));

                    $writer->close();

                    $csv = file_get_contents($filename);

                    unlink($filename);

                    return response()->streamDownload(function () use ($csv) {
                        echo $csv;
                    }, $name . '.xlsx');
                })
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Submission::query()
                    ->with(['meta'])
                    ->whereIn('status', [
                        SubmissionStatus::OnReview,
                        SubmissionStatus::OnPresentation,
                        SubmissionStatus::Editing,
                        SubmissionStatus::Published,
                    ])
                    // ->whereHas('reviews', fn ($query) => $query->whereNotNull('date_completed'))
                    ->withCount([
                        'reviews',
                        'reviews as completed_reviews_count' => fn($query) => $query->whereNotNull('date_completed'),
                    ])
                    ->withAvg(['reviews' => fn($query) => $query->whereNotNull('date_completed')], 'score'),
            )
            ->defaultSort('reviews_avg_score', 'desc')
            ->columns([
                TextColumn::make('reviews_avg_score')
                    ->extraCellAttributes([
                        'style' => 'width: 1px',
                    ])
                    ->sortable()
                    ->label('Score')
                    ->searchable(query: fn($query, $search) => $query->whereMeta('title', 'like', "%$search%"))
                    ->numeric(maxDecimalPlaces: 2),
                TextColumn::make('reviews')
                    ->extraCellAttributes([
                        'style' => 'width: 1px',
                    ])
                    ->getStateUsing(fn($record) => $record->completed_reviews_count . ' / ' . $record->reviews_count),
                TextColumn::make('id')
                    ->label('ID')
                    ->extraCellAttributes([
                        'style' => 'width: 1px',
                    ])
                    ->grow(false),
                TextColumn::make('title')
                    ->getStateUsing(fn($record) => $record->getMeta('title'))
                    ->color('primary')
                    ->openUrlInNewTab()
                    ->url(fn($record) => SubmissionResource::getUrl('view', ['record' => $record]))
                    ->wrap(),
                TextColumn::make('status')
                    ->extraAttributes([
                        'class' => 'mt-2',
                    ])
                    ->badge()
                    ->formatStateUsing(
                        fn(Submission $record) => $record->status?->value
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
