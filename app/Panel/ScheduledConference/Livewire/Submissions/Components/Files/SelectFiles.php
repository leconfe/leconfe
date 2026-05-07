<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components\Files;

use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use GuzzleHttp\Psr7\MimeType;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class SelectFiles extends SubmissionFilesTable
{
    public bool $viewOnly = true;

    public string $targetCategory;

    public array $selectableCategories = [];

    public array $allowedFileTypes = [];

    public $listeners = [
        'refreshList' => '$refresh',
    ];

    public function tableColumns(): array
    {
        return [
            TextColumn::make('media.original_file_name')
                ->label('Filename')
                ->color('primary')
                ->wrap()
                ->action(fn (Model $record) => $record->media)
                ->description(fn (Model $record) => $record->type->name),
            TextColumn::make('category'),
        ];
    }

    public function tableQuery(): Builder
    {
        $selectedCategoryIDs = $this->submission
            ->submissionFiles()
            ->select('media_id')
            ->where('category', $this->targetCategory);

        return $this->submission
            ->submissionFiles()
            ->whereNotIn('media_id', $selectedCategoryIDs)
            ->whereHas('media', function ($query) {
                $query->whereIn(
                    'mime_type',
                    collect($this->allowedFileTypes)
                        ->map(function ($type) {
                            return MimeType::fromExtension($type);
                        })
                        ->toArray()
                );
            })
            ->whereIn('category', $this->selectableCategories)
            ->getQuery();
    }

    public function bulkActions(): array
    {
        return [
            BulkAction::make('confirm')
                ->icon('iconpark-check')
                ->label('Confirm')
                ->requiresConfirmation()
                ->successNotificationTitle('Files selected successfully')
                ->action(function (Collection $records, BulkAction $action) {
                    foreach ($records as $record) {
                        $clonedMedia = $record->media->copy(
                            $this->submission,
                            $this->targetCategory,
                            'private-files'
                        );
                        $this->submission
                            ->submissionFiles()
                            ->create([
                                'submission_file_type_id' => $record->type->getKey(),
                                'category' => $this->targetCategory,
                                'media_id' => $clonedMedia->getKey(),
                            ]);
                    }
                    $this->dispatch('close-select-files');
                    $action->success();
                }),
        ];
    }
}
