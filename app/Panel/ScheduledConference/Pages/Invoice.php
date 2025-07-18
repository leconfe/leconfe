<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Models\Payment;
use Filament\Pages\Page;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

class Invoice extends Page
{
	protected static string $view = 'panel.scheduledConference.pages.invoice';

	public Payment $record;

	public function __invoke()
	{
		$user = auth()->user();

		$currentRoute = Route::getCurrentRoute();

		$this->record = $currentRoute->parameter('record');

		abort_unless($this->record, 404);

		$canAccess = $user->can('update', App::getCurrentScheduledConference()) || $user->email === $this->record->email;

		abort_unless($canAccess, 404);


		return view(static::$view, [
			'scheduledConference' => app()->getCurrentScheduledConference(),
			'record' => $this->record,
		]);
	}

	public function mount(Payment $record): void {}

	public static function shouldRegisterNavigation(): bool
	{
		return false;
	}

	public static function getRoutePath(): string
	{
		return '/payments/invoice/{record}';
	}
}
