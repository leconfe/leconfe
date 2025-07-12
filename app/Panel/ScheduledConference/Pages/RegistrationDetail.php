<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Models\Registration;
use App\Models\RegistrationForm;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Support\Facades\App;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;

class RegistrationDetail extends Page implements HasForms, HasInfolists
{
	use InteractsWithForms, InteractsWithInfolists;

	protected static string $view = 'panel.scheduledConference.pages.registration-detail';

	public Registration $record;

	public function mount(Registration $record): void
	{
		$this->authorize('update', App::getCurrentScheduledConference());
	}

	public static function shouldRegisterNavigation(): bool
	{
		return false;
	}

	public static function getRoutePath(): string
	{
		return '/registrations/{record}';
	}

	protected function getHeaderActions(): array
	{
		return [
			Action::make('edit_registration')
				->record($this->record)
				->label('Edit Registration')
				->fillForm([
					...$this->record->attributesToArray(),
					'meta' => $this->record->getAllMeta()->toArray(),
				])
				->form(
					fn(Form $form) => $form
						->operation('edit')
						->schema([
							...RegistrationForm::getFormSchema(),
							TextInput::make('number')
								->label("Invoice Number"),
							DatePicker::make('paid_at')
								->label("Payment Date")
						])
				)
				->action(function (Action $action, array $data, Registration $record) {
					$record->update($data);

					if (array_key_exists('meta', $data)) {
						$record->setManyMeta($data['meta']);
					};

					$action->successNotificationTitle('Registration Updated.');
					$action->success();

				})
		];
	}

	public function infolist(Infolist $infolist): Infolist
	{
		return $this->record->detailInfolist($infolist);
	}
}
