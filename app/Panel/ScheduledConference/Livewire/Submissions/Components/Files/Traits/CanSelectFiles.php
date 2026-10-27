<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\Traits;

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
                ->label(__('general.select_files'))
                ->extraAttributes([
                    'x-on:close-select-files.window' => new HtmlString('$wire.unmountTableAction(\'select-files\')'),
                ])
                ->infolist([
                    ShoutEntry::make('information')
                        ->color('info')
                        ->content(__('general.choose_files_to_duplicate')),
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
                ->label(__('general.upload_files'))
                ->hidden(
                    fn (): bool => $this->submission->isDeclined() ?: $this->isViewOnly()
                )
                ->modalWidth('xl')
                ->form(
                    $this->uploadFormSchema()
                )
                ->successNotificationTitle(__('general.files_added_successfully'))
                ->failureNotificationTitle(__('general.problem_adding_files'))
                ->action(
                    fn (array $data, Action $action) => $this->handleUploadAction($data, $action)
                ),
        ])
            ->button();
    }
}
