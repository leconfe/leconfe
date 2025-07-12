<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Forms\Components\TinyEditor;
use Filament\Actions\Action;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Support\Enums\Alignment;
use Livewire\Component;
use Filament\Forms\Components\Actions\Action as ActionForm;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Get;

class InvoiceSetting extends Component implements HasForms
{
	use InteractsWithForms;

	public ?array $formData = [];

	public function mount(): void
	{
		$scheduledConference = app()->getCurrentScheduledConference();

		$this->form->fill([
			'meta' => $scheduledConference->getAllMeta(),
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
				Section::make()
					->columns(1)
					->schema([
						Checkbox::make('meta.invoice_enable')
							->label('Enable Invoice'),
						Checkbox::make('meta.receipt_enable')
							->label('Enable Receipt'),
						TextInput::make('meta.organizer')
							->label('Organizer')
							->required(),
						TinyEditor::make('meta.invoice_sender_information')
							->label('Sender Information'),
						TinyEditor::make('meta.invoice_notes')
							->profile('basic')
							->label('Notes'),
						Grid::make()
							->schema([
								TextInput::make('meta.invoice_prefix_number')
									->label('Prefix Number of Invoice'),
								TextInput::make('meta.invoice_number')
									->label('Next Invoice Number'),
							]),
					]),
				Actions::make([
					ActionForm::make('save')
						->label(__('general.save'))
						->successNotificationTitle(__('general.saved'))
						->failureNotificationTitle(__('general.data_could_not_saved'))
						->action(function (ActionForm $action) {
							$formData = $this->form->getState();
							try {
								app()->getCurrentScheduledConference()->setManyMeta($formData['meta']);
								$action->sendSuccessNotification();
							} catch (\Throwable $th) {
								$action->sendFailureNotification();
							}
						}),
				])->alignLeft(),
			])
			->statePath('formData');
	}
}
