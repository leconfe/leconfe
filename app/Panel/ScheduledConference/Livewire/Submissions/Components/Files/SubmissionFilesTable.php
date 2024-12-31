<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components\Files;

use App\Actions\SubmissionFiles\UploadSubmissionFileAction;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\SubmissionFileType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\Support\MediaStream;

abstract class SubmissionFilesTable extends \Livewire\Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public Submission $submission;

    public bool $viewOnly = false;

    protected ?string $category = null;

    protected string $tableHeading = 'Files';

    protected string $tableDescription = '';

    public ?array $uploadFilesData = [];

    public function isViewOnly(): bool
    {
        return $this->viewOnly;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function tableColumns(): array
    {
        return [
            TextColumn::make('media.file_name')
                ->wrap()
                ->label(__('general.filename'))
                ->color('primary')
                ->action(fn (Model $record) => $record->media)
                ->description(fn (Model $record) => $record->type->name),
        ];
    }

    public function downloadAllAction()
    {
        return TableAction::make('download_all')
            ->icon('iconpark-download-o')
            ->label(__('general.download_all_files'))
            ->button()
            ->hidden(fn (Table $table): bool => ! $table->getQuery()->exists() || $this->isViewOnly())
            ->color('gray')
            ->action(function (TableAction $action) {
                $files = $this->submission->media()->where('collection_name', $this->category)->get();
                if ($files->count()) {
                    return MediaStream::create('files.zip')->addMedia($files);
                }
                $action->failureNotificationTitle(__('general.nothing_to_download'));
                $action->failure();
            });
    }

    public function uploadFormSchema(): array
    {
        return [
            Select::make('type')
                ->label(__('general.type'))
                ->required()
                ->options(fn () => SubmissionFileType::all()->pluck('name', 'id')->toArray())
                ->searchable(),
            SpatieMediaLibraryFileUpload::make('files')
                ->required()
                ->previewable(false)
                ->downloadable()
                ->reorderable()
                ->disk('private-files')
                ->preserveFilenames()
                ->collection($this->category)
                ->visibility('private')
                ->model(fn () => $this->submission)
                ->saveRelationshipsUsing(function (SpatieMediaLibraryFileUpload $component) {
                    $component->saveUploadedFiles();

                    $this->uploadFilesData[] = $component->getState();
                }),
        ];
    }

    public function handleUploadAction(array $data, TableAction $action)
    {
        $getUuids = array_merge(...array_map('array_values', $this->uploadFilesData));
        $files = $this->submission->media()->whereCollectionName($this->category)->whereIn('uuid', $getUuids)->get();

        foreach ($files as $file) {
            UploadSubmissionFileAction::run(
                $this->submission,
                $file,
                $this->category,
                SubmissionFileType::find($data['type'])
            );
        }

        $this->uploadFilesData = [];

        $action->success();
    }

    public function uploadAction()
    {
        return TableAction::make('upload')
            ->icon('iconpark-upload')
            ->label(__('general.upload_files'))
            ->outlined()
            ->hidden(fn (): bool => $this->isViewOnly())
            ->modalWidth('xl')
            ->form(
                $this->uploadFormSchema()
            )
            ->successNotificationTitle(__('general.files_added_successfully'))
            ->failureNotificationTitle(__('general.a_problem_adding_files'))
            ->action(
                fn (array $data, TableAction $action) => $this->handleUploadAction($data, $action)
            );
    }

    public function headerActions(): array
    {
        return [
            $this->downloadAllAction(),
            $this->uploadAction(),
        ];
    }

    public function tableActions(): array
    {
        return [
            TableAction::make('rename')
                ->icon('iconpark-edit')
                ->label(__('general.rename'))
                ->modalWidth('md')
                ->modalHeading(__('general.edit_files'))
                ->modalHeading(__('general.rename'))
                ->hidden(
                    fn (): bool => $this->isViewOnly() || $this->submission->isDeclined()
                )
                ->successNotificationTitle(__('general.file_renamed_successfully'))
                ->mountUsing(function (SubmissionFile $record, Form $form) {
                    $form->fill([
                        'file_name' => $record->media->file_name,
                    ]);
                })
                ->action(function (SubmissionFile $record, array $data, TableAction $action) {
                    $record->media->update([
                        'file_name' => $data['file_name'],
                        'name' => $data['file_name'],
                    ]);
                    $action->success();
                })
                ->modalSubmitActionLabel(__('general.rename'))
                ->form([
                    TextInput::make('file_name')
                        ->label(__('general.new_filename'))
                        ->formatStateUsing(function (SubmissionFile $record) {
                            return str($record->media->file_name)->beforeLast('.'.$record->media->extension);
                        })
                        ->dehydrateStateUsing(function (SubmissionFile $record, $state) {
                            return str($state)->append('.'.$record->media->extension);
                        })
                        ->suffix(function (SubmissionFile $record) {
                            return '.'.$record->media->extension;
                        }),
                ]),
            DeleteAction::make()
                ->hidden(function (): bool {
                    if ($this->submission->isDeclined()) {
                        return true;
                    }

                    return $this->isViewOnly();
                }),
        ];
    }

    public function tableQuery(): Builder
    {
        return $this->submission
            ->submissionFiles()
            ->with(['media'])
            ->where('category', $this->category)
            ->getQuery();
    }

    public function tableDescription(): string
    {
        return $this->tableDescription;
    }

    public function tableHeading(): string
    {
        return $this->tableHeading;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading($this->tableHeading())
            ->description($this->tableDescription())
            ->emptyStateHeading(__('general.no_files'))
            ->query($this->tableQuery())
            ->columns($this->tableColumns())
            ->headerActions($this->headerActions())
            ->actions($this->tableActions())
            ->bulkActions($this->bulkActions());
    }

    public function bulkActions(): array
    {
        return [];
    }

    public function render()
    {
        return view('panel.scheduledConference.livewire.submissions.components.files.media-file-table');
    }
}
