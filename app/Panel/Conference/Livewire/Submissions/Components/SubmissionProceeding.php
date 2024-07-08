<?php

namespace App\Panel\Conference\Livewire\Submissions\Components;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Infolists\Components\BladeEntry;
use App\Models\Proceeding;
use App\Models\Submission;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\MaxWidth;

class SubmissionProceeding extends \Livewire\Component implements HasForms, HasInfolists
{
    use InteractsWithForms, InteractsWithInfolists;

    public Submission $submission;
    public array $meta = [];
    public array $media = [];
    protected $listeners = [
        'refreshSubmissionProceeding' => '$refresh',
    ];

    public function render()
    {
        return view('panel.conference.livewire.submissions.components.submission-proceeding');
    }

    public function mount(Submission $submission)
    {
        $this->form->fill([
            'meta' => [
                'article_pages' => $this->submission->getMeta('article_pages'),
            ],
        ]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->submission)
            ->schema([
                TextEntry::make('id')
                    ->label(__('translation.submissions.submmisionProceedingLabelProceeding'))
                    ->html()
                    ->getStateUsing(function (Submission $record) {
                        if ($record->proceeding){
                            $proceedingTitle = $record->proceeding->title;
                            $proceedingRoute = route('filament.conference.resources.proceedings.view', ['record' => $record->proceeding]);

                            return <<<HTML
                                <a class="text-primary-700 hover:text-primary-800" href="$proceedingRoute">$proceedingTitle</a>
                            HTML;
                        }

                        return __('translation.submissions.submmisionProceedingGetStateUsing');
                    })
                    ->suffixActions([
                        Action::make('assign_proceeding')
                            ->button()
                            ->label(fn(Submission $record) => $record->proceeding ? __('translation.submissions.labelChangeProceeding') : __('translation.submissions.labelAssignToProceeding'))
                            ->visible(fn (Submission $record) => auth()->user()->can('editing', $record))
                            ->modalWidth(MaxWidth::ExtraLarge)
                            ->form(fn (Submission $record) => static::getFormAssignProceeding($record))
                            ->action(fn (Submission $record, array $data) => static::assignProceeding($record, $data)),
                    ]),
            ]);
    }

    public static function getFormAssignProceeding(Submission $submission): array
    {
        return [
            Select::make('proceeding_id')
                ->label(__('translation.submissions.submmisionProceedingLabelProceeding'))
                ->placeholder('None')
                ->formatStateUsing(fn () => $submission->proceeding_id ?? null)
                // ->native(false)
                // ->searchable()
                ->options(
                    fn () => [
                        'Future Proceeding' => Proceeding::query()
                            ->where('published', false)
                            ->pluck('title', 'id')
                            ->toArray(),
                        'Back Proceeding' => Proceeding::query()
                            ->where('published', true)
                            ->pluck('title', 'id')
                            ->toArray(),
                    ]
                )
        ];
    }

    public static function assignProceeding(Submission $submission, array $data)
    {
        $data['proceeding_id'] ? $submission->assignProceeding($data['proceeding_id']) : $submission->unassignProceeding();
    }

    public function form(Form $form): Form
    {
        return $form
            ->disabled(function (): bool {
                return ! auth()->user()->can('editing', $this->submission);
            })
            ->schema([
                SpatieMediaLibraryFileUpload::make('media.article-cover')
                    ->label(__('translation.submissions.submmisionProceedingLabelCoverImage'))
                    ->collection('article-cover')
                    ->model($this->submission)
                    ->image()
                    ->preserveFilenames(),
                TextInput::make('meta.article_pages')
                    ->label(__('translation.submissions.submmisionProceedingLabelPages'))
                    ->maxWidth('xs')
                    ->placeholder('e.g. 1-10'),
            ]);
    }

    public function submit()
    {
        try {
            $submission = SubmissionUpdateAction::run(
                $this->form->getState(),
                $this->submission
            );

            $this->form->model($submission)->saveRelationships();
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
