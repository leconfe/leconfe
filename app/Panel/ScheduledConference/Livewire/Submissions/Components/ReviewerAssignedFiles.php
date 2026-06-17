<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components;

use App\Models\Review;
use App\Models\ReviewerAssignedFile;
use App\Models\User;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;

class ReviewerAssignedFiles extends \Livewire\Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public Review $record;

    public User $user;

    public function mount(Review $record, User $user): void
    {
        $this->user = $user ?? auth()->user();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Assigned Files')
            ->query(
                fn (): Builder => $this->record->assignedFiles()->getQuery()
            )
            ->columns([
                TextColumn::make('submissionFile.media.original_file_name')
                    ->color('primary')
                    ->action(function (ReviewerAssignedFile $record) {
                        return Response::download(
                            $record->submissionFile->media->getPath(),
                            $record->submissionFile->media->originalFileName
                        );
                    })
                    ->description(function (ReviewerAssignedFile $record) {
                        return $record->submissionFile->type->name;
                    }),
            ]);
    }

    public function render()
    {
        return view('panel.scheduledConference.livewire.submissions.components.reviewer-assigned-files');
    }
}
