<?php

namespace App\Panel\ScheduledConference\Pages;

use App\Facades\Setting;
use App\Managers\PaymentManager;
use App\Models\Payment;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Radio;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;

class PaymentDetail extends Page
{
	protected static string $view = 'panel.scheduledConference.pages.payment-detail';

	public Payment $record;

	protected static bool $shouldRegisterNavigation = false;

	public function mount(Payment $record)
	{
		$record->load(['model', 'user']);
	}

	public static function canAccess(): bool
	{
		return true;
	}

	protected function getHeaderActions(): array
	{
		$paymentActions = collect(PaymentManager::get()->getPaymentMethodActions())->map(fn(Action $action) => $action->record($this->record)->model(Payment::class));

		return [
			ActionGroup::make($paymentActions->toArray())
				->button()
				->label('Payment'),
		];
	}

	public function infolist(Infolist $infolist): Infolist
	{
		return $infolist
			->record($this->record)
			->columns(12)
			->schema([
				Grid::make()
					->columnSpan([
						'default' => 1,
						'lg' => 8,
					])
					->schema([
						Section::make('Information')
							->schema([
								TextEntry::make('submission')
									->visible(fn(Payment $record) => $record->type == PaymentManager::TYPE_SUBMISSION_FEE)
									->state(fn(Payment $record) => $record->model?->getMeta('title') ?? '-')
				                    ->url(fn (Payment $record) => $record->model ? SubmissionResource::getUrl('view', ['record' => $record->model]) : null)
									->color('primary'),
								TextEntry::make('full_name')
									->state(function (Payment $record) {
										if ($record->type == PaymentManager::TYPE_SUBMISSION_FEE) {
											return $record->user->full_name;
										}

										if ($record->type == PaymentManager::TYPE_PARTICIPANT_FEE) {
											return $record->model->full_name;
										}
									}),
								TextEntry::make('email')
									->state(function (Payment $record) {
										if ($record->type == PaymentManager::TYPE_SUBMISSION_FEE) {
											return $record->user->email;
										}

										if ($record->type == PaymentManager::TYPE_PARTICIPANT_FEE) {
											return $record->model->email;
										}
									}),
								TextEntry::make('fee.name')
									->label("Payment Fee Name"),
								TextEntry::make('amount')
									->state(fn($record) => $record->getFormattedFee()),
							]),
					]),
				Grid::make()
					->columnSpan([
						'default' => 1,
						'lg' => 4,
					])
					->schema([
						Section::make('Additional Information')
							->schema([
								TextEntry::make('created_at')
									->label('Registered at')
									->dateTime(Setting::get('format_date') . ' ' . Setting::get('format_time')),
								TextEntry::make('invoice')
									->state('Download')
									->color('primary')
									// ->visible(fn() => app()->getCurrentScheduledConference()?->isInvoiceEnabled())
									->url(fn(Payment $record) => Invoice::getUrl(['record' => $record]))
									->openUrlInNewTab(),
								// TextEntry::make('proof_of_payment')
								// 	->state('Download')
								// 	->visible(fn(Registration $record) => $record->hasMedia('payment_proof'))
								// 	->color('primary')
								// 	->action(
								// 		InfolistAction::make('download')
								// 			->link()
								// 			->visible(fn(Registration $record) => $record->hasMedia('payment_proof'))
								// 			->action(fn(Registration $record) => $record->getFirstMedia('payment_proof')),
								// 	),
								TextEntry::make('paid_at')
									->visible(fn(Payment $record) => $record->paid_at)
									->dateTime(Setting::get('format_date') . ' ' . Setting::get('format_time'))
								// ->dateTime(Setting::get('format_date') . ' ' . Setting::get('format_time')),
								// TextEntry::make('receipt')
								// 	->state('Download')
								// 	->color('primary')
								// 	->visible(fn(Registration $record) => app()->getCurrentScheduledConference()?->isReceiptEnabled() && $record->paid_at)
								// 	->url(fn(Registration $record) => Receipt::getUrl(['record' => $record]))
								// 	->openUrlInNewTab(),
							]),
						...PaymentManager::get()->getPaymentMethodInfolist()
					]),

			]);
	}

	public static function getRoutePath(): string
	{
		return '/payments/detail/{record}';
	}
}
