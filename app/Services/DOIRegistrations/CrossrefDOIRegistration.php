<?php

namespace App\Services\DOIRegistrations;

use App\Classes\ImportExport\ExportArticleCrossref;
use App\Models\Enums\DOIStatus;
use App\Models\Submission;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
class CrossrefDOIRegistration extends BaseDOIRegistration
{
	public function getName(): string
	{
		return 'Crossref';
	}

	public function getTableActions(): array
	{
		return [
			ActionGroup::make([
				Action::make('export')
					->icon('heroicon-s-document-arrow-down')
					->color('primary')
					->label(__('translation.croessrefDOIRegistration.labelExportXML'))
					->action(function (Submission $record) {
						try {
							$xml = $this->exportXml($record);
							$filename = Str::slug($record->getKey() . '-' . $record->getMeta('title')) . '.xml';

							return response()->streamDownload(function () use ($xml) {
								echo $xml;
							}, $filename);
						} catch (\Throwable $th) {
							Notification::make()
								->danger()
								->title(__('translation.croessrefDOIRegistration.notificationTitleFailed'))
								->body($th->getMessage())
								->send();
						}
					}),
				Action::make('deposit')
					->label(__('translation.croessrefDOIRegistration.labelDepositXML'))
					->icon('heroicon-s-cloud-arrow-up')
					->color('primary')
					->action(function (Submission $record) {
						try {
							$result = $this->depositXml($record);

							if ($result) {
								Notification::make()
									->success()
									->title(__('translation.croessrefDOIRegistration.notificationTitleSuccess'))
									->send();
							}
						} catch (\Exception $e) {
							Notification::make()
								->danger()
								->title(__('translation.croessrefDOIRegistration.notificationTitleFailed'))
								->body($e->getMessage())
								->send();
						}
					}),
				Action::make('view_error')
					->label(__('translation.croessrefDOIRegistration.labelViewErrorMessage'))
					->color('danger')
					->icon('heroicon-o-x-mark')
					->hidden(fn(Submission $record) => $record->doi?->status !== DOIStatus::Error)
					->modalWidth(MaxWidth::Large)
					->modalSubmitAction(false)
					->modalCancelAction(false)
					->infolist(function (Infolist $infolist, Submission $record) {
						$doi = $record->doi;


						$infolist->state([
							'message' => $doi->getMeta('crossref_message'),
						]);

						$infolist->schema([
							TextEntry::make('message')
								->hiddenLabel()
								->formatStateUsing(function (?string $state) {
									return new HtmlString($state);
								}),
						]);

						return $infolist;
					})
			])
				->size(ActionSize::Small)
				->outlined()
				->label(__('translation.croessrefDOIRegistration.labelCrossref'))
				->button()
				->hidden(fn(Submission $record) => !$record->doi),
		];
	}

	public function getSettingFormSchema(): array
	{
		return [
			// Section::make('Automatic Deposit')
			// 	->schema([
			// 		Placeholder::make('doi_automatic_deposit_description')
			// 			->content("The DOI registration and metadata can be automatically deposited with the selected registration agency whenever an item with a DOI is published. Automatic deposit will happen at scheduled intervals and each DOI's registration status can be monitored from the DOI management page")
			// 			->hiddenLabel(),
			// 		Checkbox::make('meta.doi_automatic_deposit')
			// 			->label('Automatically deposit DOIs')
			// 	]),5
			Placeholder::make(__('translation.croessrefDOIRegistration.makeCrossrefSettings'))
				->content(__('translation.croessrefDOIRegistration.contentCrossrefSettings')),
			TextInput::make('meta.doi_crossref_depositor_name')
				->label(__('translation.croessrefDOIRegistration.labelDepositorName'))
				->helperText(__('translation.croessrefDOIRegistration.helperTextCrossrefDepositorName'))
				->required(),
			TextInput::make('meta.doi_crossref_depositor_email')
				->label(__('translation.croessrefDOIRegistration.labelDepositorName'))
				->helperText(__('translation.croessrefDOIRegistration.helperTextDepositorName'))
				->required(),
			Placeholder::make('information')
				->hiddenLabel()
				->content(new HtmlString('
				<div class="prose prose-sm max-w-none">
					<p>' . __('translation.croessrefDOIRegistration.contentInformationIfYouWould') . ' <a href="http://www.crossref.org/">' . __('translation.croessrefDOIRegistration.contentInformationCrossref') . '</a> ' . __('translation.croessrefDOIRegistration.contentInformationyYouWillNeed') . ' <a href="https://www.crossref.org/documentation/member-setup/account-credentials/">' . __('translation.croessrefDOIRegistration.contentInformationCrossrefAccountCredentials') . '</a> ' . __('translation.croessrefDOIRegistration.contentInformationIntoTheUsername') . '</p>
					<p>' . __('translation.croessrefDOIRegistration.contentInformationDependingOn') . '</p>
					<ul>
						<li>' . __('translation.croessrefDOIRegistration.contentInformatiIfYouAreUsing') . ' <a href="https://www.crossref.org/documentation/member-setup/account-credentials/#00376">' . __('translation.croessrefDOIRegistration.contentInformatiSharedUsernameAndPassword') . '</a></li>
						<li>' . __('translation.croessrefDOIRegistration.contentInformatiIfYouAreUsingA') . ' <a href="https://www.crossref.org/documentation/member-setup/account-credentials/#00368">' . __('translation.croessrefDOIRegistration.contentInformatiPersonalAccount') . '</a> ' . __('translation.croessrefDOIRegistration.contentInformatiContentInformatiPersonalAccount') . '</li>
						<li>' . __('translation.croessrefDOIRegistration.contentInformatiIYouDoNotKnow') . ' <a href="https://support.crossref.org/">' . __('translation.croessrefDOIRegistration.contentInformatiCrossrefSupport') . '</a> ' . __('translation.croessrefDOIRegistration.contentInformatiForAssistance') . '</li>
					</ul>
				</div>
			')),	
			TextInput::make('meta.doi_crossref_username')
				->label(__('translation.croessrefDOIRegistration.labelUsername'))
				->helperText(__('translation.croessrefDOIRegistration.helperTextUsername')),
			TextInput::make('meta.doi_crossref_password')
				->label(__('translation.croessrefDOIRegistration.labelPassword'))
				->password()
				->revealable()
				->required(),
			Checkbox::make('meta.doi_crossref_test')
				->label(__('translation.croessrefDOIRegistration.labelUseTheCrossrefTestAPI'))
				->inline()
		];
	}

	public function exportXml(Submission $submission)
	{
		$export = new ExportArticleCrossref($submission);
		return $export->exportXml();
	}

	public function depositXml(Submission $submission)
	{
		$export = new ExportArticleCrossref($submission);
		return $export->depositXml();
	}
}
