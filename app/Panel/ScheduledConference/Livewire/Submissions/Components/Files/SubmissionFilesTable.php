<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components\Files;

use App\Actions\SubmissionFiles\UploadSubmissionFileAction;
use App\Classes\Log;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\SubmissionFileType;
use Filament\Forms\Components\Select;
use App\Forms\Components\SpatieMediaLibraryFileUpload;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            TextColumn::make('id')
                ->wrap(),
            TextColumn::make('media.original_file_name')
                ->wrap()
                ->label(__('general.filename'))
                ->color('primary')
                ->action(function (SubmissionFile $record){
                    $name = implode('-', [
                        $record->getKey(),
                        str_replace(['/', '\\'], '-', $record->user->full_name),
                        Str::limit(basename($record->media->name), 100, '')
                    ]);

                    return response()->download($record->media->getPath(), $name . '.' . $record->media->extension);
                })
                ->description(fn (SubmissionFile $record) => $record->type->name),
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
                    $name = implode('-' , [
                        $this->submission->getKey(),
                        'files',
                    ]);

                    return MediaStream::create($name . '.zip')->addMedia($files);
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
                ->options(fn () => SubmissionFileType::ordered()->pluck('name', 'id')->toArray())
                ->searchable(),
            SpatieMediaLibraryFileUpload::make('files')
                ->required()
                ->previewable(false)
                ->downloadable()
                ->reorderable()
                ->disk('private-files')
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
            $submissionFile = UploadSubmissionFileAction::run(
                $this->submission,
                $file,
                $this->category,
                SubmissionFileType::find($data['type'])
            );

            Log::make(
                name: 'submission',
                subject: $this->submission,
                description: __('general.submission_file_uploaded_activity', [
                    'id' => $submissionFile->getKey(),
                    'name' => $file->original_file_name,
                    'category' => $this->category,
                ]),
                event: 'submission-file-upload',
            )
                ->by(auth()->user())
                ->save();
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
                        'file_name' => $record->media->name,
                    ]);
                })
                ->action(function (SubmissionFile $record, array $data, TableAction $action) {
                    try {
                        DB::beginTransaction();

                        $media = $record->media;

                        $media->file_name = $data['file_name'];
                        $media->name = $data['file_name'];

                        $log = Log::make(
                            name: 'submission',
                            subject: $this->submission,
                            description: __('general.submission_file_renamed_activity', [
                                'id' => $record->getKey(),
                                'oldName' => $media->getOriginal('name'),
                                'newName' => $media->name,
                                'category' => $this->category,
                            ]),
                            event: 'submission-file-rename',
                        )
                            ->by(auth()->user());

                        $media->save();
                        $log->save();

                        DB::commit();
                    } catch (\Throwable $th) {
                        DB::rollBack();
                        throw $th;
                    }
                    $action->success();
                })
                ->modalSubmitActionLabel(__('general.rename'))
                ->form([
                    TextInput::make('file_name')
                        ->label(__('general.new_filename'))
                        ->formatStateUsing(function (SubmissionFile $record) {
                            return $record->media->name;
                        })
                        ->dehydrateStateUsing(function (SubmissionFile $record, $state) {
                            return str($state)->append('.'.$record->media->extension);
                        })
                        ->suffix(function (SubmissionFile $record) {
                            return '.'.$record->media->extension;
                        }),
                ]),
            DeleteAction::make()
                ->authorize('deleteFile', $this->submission)
                ->using(function (SubmissionFile $record) {
                    try {
                        DB::beginTransaction();

                        $record->delete();

                        Log::make(
                            name: 'submission',
                            subject: $this->submission,
                            description: __('general.submission_file_deleted_activity', [
                                'id' => $record->getKey(),
                                'name' => $record->media->file_name,
                            ]),
                            event: 'submission-file-deleted',
                        )
                            ->by(auth()->user())
                            ->save();

                        DB::commit();
                    } catch (\Throwable $th) {
                        DB::rollBack();

                        throw $th;
                    }
                })
                ->hidden(fn () => $this->isViewOnly()),
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
