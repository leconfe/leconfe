<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components;

use App\Constants\SubmissionFileCategory;
use App\Models\Media;
use App\Models\Review;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Spatie\MediaLibrary\Support\MediaStream;

class ReviewerFiles extends \Livewire\Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public Review $record;

    public bool $viewOnly = false;

    public function isViewOnly(): bool
    {
        return $this->viewOnly;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Reviewer Files')
            ->headerActions([
                Action::make('download_all')
                    ->hidden(
                        fn (): bool => $this->isViewOnly()
                    )
                    ->icon('iconpark-download-o')
                    ->label('Download All Files')
                    ->button()
                    ->color('gray')
                    ->action(function () {
                        $downloads = $this->record->getMedia(SubmissionFileCategory::REVIEWER_FILES);

                        return MediaStream::create('files.zip')->addMedia($downloads);
                    }),
                Action::make('upload')
                    ->label('Upload Files')
                    ->hidden(
                        fn (): bool => $this->isViewOnly()
                    )
                    ->icon('iconpark-upload')
                    ->form([
                        SpatieMediaLibraryFileUpload::make('reviewer-files')
                            ->required()
                            ->previewable(false)
                            ->downloadable()
                            ->reorderable()
                            ->disk('private-files')
                            ->preserveFilenames()
                            ->collection(SubmissionFileCategory::REVIEWER_FILES)
                            ->visibility('private')
                            ->model(fn () => $this->record)
                            ->saveRelationshipsUsing(
                                static fn (SpatieMediaLibraryFileUpload $component) => $component->saveUploadedFiles()
                            ),
                    ]),
            ])
            ->actions([
                DeleteAction::make()
                    ->hidden(
                        fn (): bool => $this->isViewOnly()
                    ),
            ])
            ->query(
                fn (): Builder => $this->record
                    ->media()
                    ->where(
                        'collection_name',
                        SubmissionFileCategory::REVIEWER_FILES
                    )
                    ->getQuery()
            )
            ->columns([
                TextColumn::make('file_name')
                    ->color('primary')
                    ->action(
                        fn (Media $record) => $record
                    ),
            ]);
    }

    public function render()
    {
        return view('panel.scheduledConference.livewire.submissions.components.reviewer-files');
    }
}
