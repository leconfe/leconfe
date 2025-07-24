<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\Traits;

use App\Infolists\Components\LivewireEntry;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\SelectFiles;
use Awcodes\Shout\Components\ShoutEntry;
use Filament\Infolists\Components\Livewire;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Support\HtmlString;

trait CanSelectFiles
{
    abstract public function getTargetCategory(): string;

    abstract public function getSelectableCategories(): array;

    public function uploadAction()
    {
        return ActionGroup::make([
            Action::make('select-files')
                ->hidden($this->isViewOnly())
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->icon('iconpark-check')
                ->label('Select Files')
                ->extraAttributes([
                    'x-on:close-select-files.window' => new HtmlString('$wire.unmountTableAction(\'select-files\')'),
                ])
                ->infolist([
                    ShoutEntry::make('information')
                        ->color('info')
                        ->content('Choose the files to create duplicates.'),
                    Livewire::make(
                            SelectFiles::class,
                            [
                                'submission' => $this->submission,
                                'targetCategory' => $this->getTargetCategory(),
                                'selectableCategories' => $this->getSelectableCategories(),
                                'allowedFileTypes' => config('media-library.accepted_file_types'),
                                'lazy' => true,
                            ]
                        ),
                ]),
            Action::make('upload')
                ->icon('iconpark-upload')
                ->label('Upload Files')
                ->hidden(
                    fn (): bool => $this->submission->isDeclined() ?: $this->isViewOnly()
                )
                ->modalWidth('xl')
                ->form(
                    $this->uploadFormSchema()
                )
                ->successNotificationTitle('Files added successfully')
                ->failureNotificationTitle('There was a problem adding the files')
                ->action(
                    fn (array $data, Action $action) => $this->handleUploadAction($data, $action)
                ),
        ])
            ->button();
    }
}
