<?php

namespace App\Panel\ScheduledConference\Livewire;

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
use Filament\Forms\Components\Hidden;
use Filament\Forms\Get;

class RegistrationFormSetting extends Component implements HasForms
{
	use InteractsWithForms;

	public ?array $formData = [];

	public function mount(): void
	{
		$scheduledConference = app()->getCurrentScheduledConference();
		// dd($scheduledConference->getMeta('registration_form'));
		$this->form->fill([
			'registration_form' => $scheduledConference->getMeta('registration_form'),
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
				Builder::make('registration_form')
					->collapsible()
					->persistCollapsed()
					->addActionAlignment(Alignment::Start)
					->blockIcons()
					->blockPreviews(areInteractive: true)
					->hiddenLabel()
					->blockNumbers(false)
					->editAction(fn(ActionForm $action) => $action->slideOver())
					->addAction(fn(ActionForm $action) => $action->slideOver())
					->addBetweenAction(fn(ActionForm $action) => $action->slideOver())
					->blocks([
						Builder\Block::make('single_text_box')
							->schema([
								Hidden::make('key')
									->dehydrateStateUsing(fn(Get $get) => Str::snake($get('label'))),
								TextInput::make('label')
									->required(),
								Textarea::make('description'),
								Toggle::make('required')
									->default(false),
								Hidden::make('is_deleteable')
									->default(true),
							])
							->label(fn(?array $state) => $state['label'] ?? 'Single Text Box')
							->preview('panel.scheduledConference.form.registrationForm.preview.answer'),
					]),
				Actions::make([
					ActionForm::make('save')
						->label(__('general.save'))
						->successNotificationTitle(__('general.saved'))
						->failureNotificationTitle(__('general.data_could_not_saved'))
						->action(function (ActionForm $action) {
							$formData = $this->form->getState();
							try {
								app()->getCurrentScheduledConference()->setMeta('registration_form', $formData['registration_form']);
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
