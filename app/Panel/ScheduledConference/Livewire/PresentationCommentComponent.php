<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Actions\ScheduledConferences\ScheduledConferenceUpdateAction;
use App\Models\Presentation;
use App\Models\PresentationComment;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Livewire\Component;

class PresentationCommentComponent extends Component implements HasForms, HasActions
{
	use InteractsWithForms, InteractsWithActions;

	public ?array $formData = [];

	public PresentationComment $record;

	public function mount(PresentationComment $record): void
	{
		// $this->form->fill([]);
	}


	public function deleteAction(): Action
	{
		return Action::make('delete')
			->color('danger')
			->requiresConfirmation()
			->action(function (array $arguments) {
				$this->record->delete();

				$this->dispatch('refreshComments')->to(PresentationDiscussion::class); 
			});
	}

	public function render()
	{
		return view('panel.scheduledConference.livewire.presentation-comment-component');
	}
}
