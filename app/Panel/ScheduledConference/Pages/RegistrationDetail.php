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

class RegistrationDetail extends Page  implements HasForms, HasInfolists
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
			Action::make('download_invoice')
				->label('Download Invoice')
				->color('gray')
				->requiresConfirmation()
				->action(fn() => ''),
			Action::make('edit')
				->requiresConfirmation()
				->action(fn() => ''),
			
		];
	}

	public function infolist(Infolist $infolist): Infolist
	{
		return $infolist
			->record($this->record)
			->schema([
				Grid::make()
					->columns(12)
					->schema([
						Section::make('Information')
							->schema([
								TextEntry::make('full_name'),
								TextEntry::make('email'),
								TextEntry::make('type'),
								TextEntry::make('cost')
									->getStateUsing(fn(Registration $record) => money($record->cost, $record->currency, true)->formatWithoutZeroes()),
								TextEntry::make('currency')
									->getStateUsing(fn(Registration $record) => Currency::find($record->currency)?->name),
								...$this->record->getInfolistEntries(),
							])
							->columnSpan([
								'default' => 1,
								'lg' => 8,
							]),
						Grid::make(1)
							->schema([
								Section::make()
									->schema([
										TextEntry::make('created_at')
											->label('Registered At')
											->dateTime(Setting::get('format_date') . ' ' . Setting::get('format_time')),
										TextEntry::make('paid_at')
											->visible(fn(Registration $record) => $record->paid_at),

										// InfolistActions::make([
										// 	InfolistAction::make('resetStars')
										// 		->icon('heroicon-m-x-mark')
										// 		->color('danger')
										// 		->requiresConfirmation()
										// 		->action(function ($data) {
										// 			dd($data);
										// 		})
										// ]),
									]),
								Section::make('Submissions')
									->visible(fn($record) => $record->userSubmissions->isNotEmpty())
									->schema([
										RepeatableEntry::make('userSubmissions')
											->hiddenLabel()
											->schema([
												TextEntry::make('title')
													->url(fn(Submission $record) => SubmissionResource::getUrl('view', ['record' => $record]))
													->hiddenLabel()
													->color('primary')
													->openUrlInNewTab()
													->getStateUsing(fn(Submission $record) => $record->getMeta('title') ?? '-'),
											])
											->contained(false)
									]),
							])
							->columnSpan([
								'default' => 1,
								'lg' => 4,
							]),
					])

			]);
	}
}
