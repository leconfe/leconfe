<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Models\Participant;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Submission;
use Filament\Pages\Page;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Squire\Models\Country;

class Receipt extends Page
{
	protected static string $view = 'panel.scheduledConference.pages.receipt';

	public Payment $record;

	public function __invoke()
	{
		$user = auth()->user();

		$currentRoute = Route::getCurrentRoute();

		$this->record = $currentRoute->parameter('record');

		$canAccess = $this->record && auth()->user()->can('view', $this->record);

		abort_unless($canAccess, 404);

		$data = [
			'scheduledConference' => app()->getCurrentScheduledConference(),
			'record' => $this->record,
			'baseAmount' => (float) $this->record->getMeta('base_amount', $this->record->fee?->amount ?? 0),
			'additionalItems' => collect($this->record->getMeta('additional_items', []))
				->filter(fn ($item) => is_array($item) && data_get($item, 'name'))
				->values(),
		];
		
		if ($this->record->model instanceof Submission) {
			$user = $this->record->model->user;
			$data['user_fullname'] = $user->full_name;
			$data['user_affiliation'] = $user->getMeta('affiliation');
			$data['user_address_line'] = $user->getMeta('address_line');
			$data['user_city'] = $user->getMeta('city');
			$data['user_post_code'] = $user->getMeta('post_code');
			$data['user_country_name'] = Country::find($user->getMeta('country'))?->name;
		} elseif ($this->record->model instanceof Participant) {
			$participant = $this->record->model;
			$data['user_fullname'] = $participant->full_name;
			$data['user_affiliation'] = $participant->getMeta('affiliation');
			$data['user_address_line'] = $participant->getMeta('address_line');
			$data['user_city'] = $participant->getMeta('city');
			$data['user_post_code'] = $participant->getMeta('post_code');
			$data['user_country_name'] = Country::find($participant->getMeta('country'))?->name;
		}



		return view(static::$view, $data);
	}

	public function mount(Payment $record): void {}

	public static function shouldRegisterNavigation(): bool
	{
		return false;
	}

	public static function getRoutePath(): string
	{
		return '/payments/receipt/{record}';
	}
}
