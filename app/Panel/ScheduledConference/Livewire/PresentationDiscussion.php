<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Forms\Components\TinyEditor;
use App\Models\Presentation;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Livewire\Component;
use Stevebauman\Purify\Facades\Purify;

class PresentationDiscussion extends Component implements HasForms, HasActions
{
	use InteractsWithForms, InteractsWithActions;

	public ?array $formData = [];

	public Presentation $record;

	public function mount(Presentation $record): void
	{
		$this->form->fill([]);

		$this->loadComments();
	}

	public function form(Form $form): Form
	{
		return $form
			->schema([
				TinyEditor::make('content')
					->hiddenLabel()
                    ->minHeight(100),
			])
			->statePath('formData');
	}

	public function submit()
	{
		$data = $this->form->getState();

		$comment = $this->record->comments()->create(['user_id' => auth()->id()]);
		$comment->setMeta('content', $data['content']);

		Notification::make()
			->title('Comment Success')
			->success()
			->send();

		$this->form->fill([]);

		$this->loadComments();
	}

	#[On('deleteComment')] 
	public function deleteComment($commentId)
	{
		$this->record->comments()->where('id', $commentId)->delete();

		$this->loadComments();
	}

	#[On('refreshComments')] 
	public function loadComments()
	{
		$this->record->load(['comments' => fn($query) => $query->latest()->whereNull('parent_id')->with(['user', 'meta', 'childs' => ['user', 'meta']])]);
	}

	public function render()
	{
		return view('panel.scheduledConference.livewire.presentation-discussion');
	}
}
