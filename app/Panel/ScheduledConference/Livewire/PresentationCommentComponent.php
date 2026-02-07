<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Actions\ScheduledConferences\ScheduledConferenceUpdateAction;
use App\Forms\Components\TinyEditor;
use App\Models\Presentation;
use App\Models\PresentationComment;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Livewire\Attributes\On;
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

	public function editAction(): Action
	{
		return Action::make('edit')
			->color('info')
			->modalWidth(MaxWidth::ExtraLarge)
			->fillForm([
				'content' => $this->record->getMeta('content')
			])
			->visible(fn() => auth()->user()->can('edit', $this->record))
			->form(fn($form) => $this->form($form))
			->action(function (array $data) {
				$this->record->setMeta('content', $data['content']);
			});
	}

	public function form(Form $form): Form
	{
		return $form
			->schema([
				TinyEditor::make('content')
					->hiddenLabel()
					->minHeight(100),
			]);
	}

	public function deleteAction(): Action
	{
		return Action::make('delete')
			->color('danger')
			->requiresConfirmation()
			->visible(fn() => auth()->user()->can('edit', $this->record))
			->action(function (array $arguments) {
				// $this->record->delete();
				
				$this->dispatch('deleteComment', commentId: $this->record->getKey())->to(PresentationDiscussion::class);
			});
	}

	public function replyAction(): Action
	{
		return Action::make('reply')
			->label('Reply')
			->icon('heroicon-o-chat-bubble-left-ellipsis')
			->link()
			->color('gray')
			->modalWidth(MaxWidth::ExtraLarge)
			->form(fn($form) => $this->form($form))
			->action(function ($data) {
				$comment = $this->record->childs()->create([
					'user_id' => auth()->id(),
					'presentation_id' => $this->record->presentation_id,
				]);
				$comment->setMeta('content', $data['content']);

				Notification::make()
					->title('Comment Success')
					->success()
					->send();

				$this->loadData();
			});
	}

	public function loadData()
	{
		$this->record->load(['user', 'meta', 'childs' => ['user', 'meta']]);
	}

	public function render()
	{
		return view('panel.scheduledConference.livewire.presentation-comment-component');
	}
}
