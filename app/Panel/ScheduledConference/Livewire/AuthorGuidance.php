<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Actions\ScheduledConferences\ScheduledConferenceUpdateAction;
use App\Forms\Components\TinyEditor;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;

class AuthorGuidance extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill([
            ...app()->getCurrentScheduledConference()->attributesToArray(),
            'meta' => app()->getCurrentScheduledConference()->getAllMeta(),
        ]);
    }

    public function render()
    {
        return view('forms.form');
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(app()->getCurrentScheduledConference())
            ->schema([
                Section::make(__('general.author_guidance'))
                    ->columns(1)
                    ->schema([
                        TinyEditor::make('meta.author_guidelines')
                            ->label(__('general.author_guidelines'))
                            ->helperText(__('general.guidance_authors_might_need_to_know'))
                            ->toolbar('bold italic superscript subscript | link | blockquote bullist numlist')
                            ->plugins('autoresize link wordcount lists'),
                        TinyEditor::make('meta.before_you_begin')
                            ->label(__('general.before_you_begin'))
                            ->helperText(__('general.brief_explanation'))
                            ->toolbar('bold italic superscript subscript | link | blockquote bullist numlist')
                            ->plugins('autoresize link wordcount lists'),
                        TinyEditor::make('meta.submission_checklist')
                            ->label(__('general.submission_checklist'))
                            ->helperText(__('general.brief_explanation'))
                            ->toolbar('bold italic superscript subscript | link | blockquote bullist numlist')
                            ->plugins('autoresize link wordcount lists'),
                    ]),
                Actions::make([
                    Action::make('save')
                        ->label(__('general.save'))
                        ->successNotificationTitle(__('general.saved'))
                        ->failureNotificationTitle(__('general.data_could_not_saved'))
                        ->action(function (Action $action) {
                            $formData = $this->form->getState();
                            try {
                                ScheduledConferenceUpdateAction::run(app()->getCurrentScheduledConference(), $formData);
                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                $action->sendFailureNotification();
                                throw $th;
                            }
                        }),
                ])->alignLeft(),
            ])
            ->statePath('formData');
    }
}
