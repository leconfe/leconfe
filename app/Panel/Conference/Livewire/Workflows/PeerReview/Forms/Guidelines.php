<?php

namespace App\Panel\Conference\Livewire\Workflows\PeerReview\Forms;

use App\Panel\Conference\Livewire\Workflows\Concerns\InteractWithTenant;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;
use Stevebauman\Purify\Facades\Purify;

class Guidelines extends \Livewire\Component implements HasForms
{
    use InteractsWithForms, InteractWithTenant;

    public string $reviewGuidelines;

    public string $competingInterests;

    public function mount(): void
    {
        $this->form->fill([
            'reviewGuidelines' => $this->conference->getMeta('review_guidelines', ''),
            'competingInterests' => $this->conference->getMeta('competing_interests', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TinyEditor::make('reviewGuidelines')
                    ->label(__('translation.guidelines.labelReviewGuidelines'))
                    ->minHeight(300),
                TinyEditor::make('competingInterests')
                    ->label(__('translation.guidelines.labelReviewGuidelines'))
                    ->minHeight(300),
            ]);
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $this->conference->setMeta('review_guidelines', Purify::clean($data['reviewGuidelines']));
        $this->conference->setMeta('competing_interests', Purify::clean($data['competingInterests']));

        Notification::make()
            ->title(__('translation.guidelines.titleSuccess'))
            ->body(__('translation.guidelines.bodyTheGuidelinesHaveBeenUpdated'))
            ->success()
            ->send();
    }

    public function render()
    {
        return view('panel.conference.livewire.workflows.peer-review.forms.guidelines');
    }
}
