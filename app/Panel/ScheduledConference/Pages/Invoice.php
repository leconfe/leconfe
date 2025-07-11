<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Facades\Setting;
use App\Models\Registration;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Actions as InfolistActions;
use Filament\Infolists\Components\Actions\Action as InfolistAction;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Support\Facades\App;
use Squire\Models\Currency;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Illuminate\Support\Facades\Route;

class Invoice extends Page
{
	protected static string $view = 'panel.scheduledConference.pages.invoice';

	public Registration $record;

	public function __invoke()
	{
		$currentRoute = Route::getCurrentRoute();

		$this->record = $currentRoute->parameter('record');
		
		abort_if(! $this->record, 404);

		return view(static::$view, [
			'scheduledConference' => app()->getCurrentScheduledConference(),
			'record' => $this->record,
		]);
	}

	public function mount(Registration $record): void {}

	public static function shouldRegisterNavigation(): bool
	{
		return false;
	}

	public static function getRoutePath(): string
	{
		return '/registrations/invoice/{record}';
	}
}
