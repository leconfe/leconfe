<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components\Files;

use App\Actions\Submissions\CloneSubmissionFilesToReviewRoundAction;
use App\Constants\SubmissionFileCategory;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\SubmissionReviewRound;
use Awcodes\Shout\Components\Shout;
use Filament\Forms\Components\CheckboxList;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Spatie\MediaLibrary\Support\MediaStream;

class PaperFiles extends SubmissionFilesTable
{
    protected ?string $category = SubmissionFileCategory::PAPER_FILES;

    protected string $tableHeading;

    public function __construct()
    {
        $this->tableHeading = __('general.papers');
    }

    public function mount(Submission $submission): void
    {
        $this->submission = $submission;
        $this->reviewRoundId = $submission->activeReviewRound?->getKey()
            ?? $submission->latestReviewRound?->getKey();
    }

    #[On('peer-review-round-selected')]
    public function onReviewRoundSelected(int $roundId): void
    {
        $this->reviewRoundId = $roundId;
        $this->resetTable();
    }

    public function getSelectedRoundProperty(): ?SubmissionReviewRound
    {
        if (! $this->reviewRoundId) {
            return null;
        }

        return $this->submission->reviewRounds()
            ->whereKey($this->reviewRoundId)
            ->first();
    }

    public function getPreviousReviewRoundProperty(): ?SubmissionReviewRound
    {
        $selectedRound = $this->selectedRound;

        if (! $selectedRound) {
            return null;
        }

        return $this->submission->reviewRounds()
            ->where('round_number', '<', $selectedRound->round_number)
            ->orderByDesc('round_number')
            ->first();
    }

    public function getPreviousRoundFilesProperty(): Collection
    {
        if (! $this->previousReviewRound) {
            return collect();
        }

        return $this->submission->submissionFiles()
            ->with(['media', 'type'])
            ->where('category', SubmissionFileCategory::PAPER_FILES)
            ->where('review_round_id', $this->previousReviewRound->getKey())
            ->orderBy('id')
            ->get();
    }

    public function headerActions(): array
    {
        return [
            ActionGroup::make([
                $this->selectFilesAction(),
                ...parent::headerActions(),
            ])
                ->button()
                ->color('gray')
                ->label(__('general.actions'))
                ->hidden(fn (): bool => $this->isViewOnly()),
        ];
    }

    public function downloadAllAction(): TableAction
    {
        return TableAction::make('download_all')
            ->icon('heroicon-o-arrow-down-tray')
            ->label(__('general.download_all_files'))
            ->color('primary')
            ->hidden(fn (): bool => $this->isViewOnly() || ! $this->tableQuery()->exists())
            ->action(function (TableAction $action) {
                $mediaIds = $this->tableQuery()->pluck('media_id');
                $files = $this->submission->media()
                    ->whereIn('id', $mediaIds)
                    ->get();

                if ($files->count()) {
                    $name = implode('-', [
                        $this->submission->getKey(),
                        'files',
                    ]);

                    return MediaStream::create($name.'.zip')->addMedia($files);
                }

                $action->failureNotificationTitle(__('general.nothing_to_download'));
                $action->failure();
            });
    }

    public function uploadAction()
    {
        return TableAction::make('upload')
            ->icon('heroicon-o-cloud-arrow-up')
            ->label(__('general.upload_files'))
            ->color('success')
            ->hidden(fn (): bool => $this->isViewOnly())
            ->modalWidth('xl')
            ->form($this->uploadFormSchema())
            ->successNotificationTitle(__('general.files_added_successfully'))
            ->failureNotificationTitle(__('general.a_problem_adding_files'))
            ->action(
                fn (array $data, TableAction $action) => $this->handleUploadAction($data, $action)
            );
    }

    protected function shouldFilterByReviewRound(): bool
    {
        return true;
    }

    protected function resolveUploadReviewRoundId(): ?int
    {
        return $this->reviewRoundId;
    }

    protected function isSelectedRoundOpen(): bool
    {
        if (! $this->reviewRoundId) {
            return false;
        }

        return (bool) $this->submission->reviewRounds()
            ->whereKey($this->reviewRoundId)
            ->open()
            ->exists();
    }

    public function isViewOnly(): bool
    {
        if (! $this->isSelectedRoundOpen()) {
            return true;
        }

        return $this->viewOnly || ! auth()->user()->can('uploadPaper', $this->submission);
    }

    public function selectFilesAction(): TableAction
    {
        return TableAction::make('select-files')
            ->label(__('general.select_files'))
            ->icon('heroicon-o-document-duplicate')
            ->color('warning')
            ->modalWidth('xl')
            ->modalHeading(fn () => $this->previousReviewRound
                ? __('general.select_files').' '.__('general.round').' '.$this->previousReviewRound->round_number
                : __('general.select_files'))
            ->hidden(fn (): bool => ! $this->canTakeFromPreviousRound())
            ->form([
                CheckboxList::make('file_ids')
                    ->label(__('general.files_from_previous_round'))
                    ->options(function () {
                        return $this->previousRoundFiles
                            ->mapWithKeys(fn (SubmissionFile $file) => [
                                $file->getKey() => $file->media?->name ?? 'File #'.$file->getKey(),
                            ])
                            ->toArray();
                    })
                    ->descriptions(function () {
                        return $this->previousRoundFiles
                            ->mapWithKeys(fn (SubmissionFile $file) => [
                                $file->getKey() => $file->type->name.' ('.$file->category.')',
                            ])
                            ->toArray();
                    }),
            ])
            ->successNotificationTitle(__('general.files_added_successfully'))
            ->action(function (array $data, TableAction $action) {
                $selectedFileIds = collect($data['file_ids'] ?? [])
                    ->filter(fn ($id) => is_numeric($id))
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                $clonedFileIds = CloneSubmissionFilesToReviewRoundAction::run(
                    $this->submission,
                    $this->selectedRound,
                    $selectedFileIds
                );

                if ($clonedFileIds === []) {
                    $action->failureNotificationTitle(__('general.no_files'));
                    $action->failure();

                    return;
                }

                $this->resetTable();
                $action->success();
            });
    }

    protected function canTakeFromPreviousRound(): bool
    {
        return $this->isSelectedRoundOpen()
            && $this->previousReviewRound !== null
            && $this->previousRoundFiles->isNotEmpty()
            && auth()->user()?->can('uploadPaper', $this->submission);
    }

    public function uploadFormSchema(): array
    {
        return [
            Shout::make('information')
                ->content(__('general.after_uploading_files_system_will_send_notification_to_editor')),
            ...parent::uploadFormSchema(),
        ];
    }
}
