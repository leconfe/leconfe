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
                    ->label('Export XML')
                    ->action(function (Submission $record) {
                        try {
                            $xml = $this->exportXml($record);
                            $filename = Str::slug($record->getKey().'-'.$record->getMeta('title')).'.xml';

                            return response()->streamDownload(function () use ($xml) {
                                echo $xml;
                            }, $filename);
                        } catch (\Throwable $th) {
                            // throw $th;
                            Notification::make()
                                ->danger()
                                ->title(__('general.failed_to_export'))
                                ->body($th->getMessage())
                                ->send();
                        }
                    }),
                Action::make('deposit')
                    ->label(__('general.deposit_XML'))
                    ->icon('heroicon-s-cloud-arrow-up')
                    ->color('primary')
                    ->action(function (Submission $record) {
                        try {
                            $result = $this->depositXml($record);

                            if ($result) {
                                Notification::make()
                                    ->success()
                                    ->title(__('general.deposit_success'))
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title(__('general.failed_to_deposit'))
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                Action::make('view_error')
                    ->label(__('general.view_error_message'))
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->hidden(fn (Submission $record) => $record->doi?->status !== DOIStatus::Error)
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
                    }),
            ])
                ->size(ActionSize::Small)
                ->outlined()
                ->label(__('general.crossref'))
                ->button()
                ->hidden(fn (Submission $record) => ! $record->doi),
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
            Placeholder::make('Crossref Settings')
                ->label(__('general.crossref_settings'))
                ->content(__('general.following_items_required_successfull_crossref_deposit')),
            TextInput::make('meta.doi_crossref_depositor_name')
                ->label(__('general.depositor_name'))
                ->helperText(__('general.name_organization_included_with_deposited_meta_data'))
                ->required(),
            TextInput::make('meta.doi_crossref_depositor_email')
                ->label(__('general.depositor_email'))
                ->helperText(__('general.email_address_individual_responsible'))
                ->required(),
            Placeholder::make('information')
                ->hiddenLabel()
                ->content(new HtmlString(__('general.information_registration_doi'))),
            TextInput::make('meta.doi_crossref_username')
                ->label(__('general.username'))
                ->helperText(__('general.username_crossref')),
            TextInput::make('meta.doi_crossref_password')
                ->label(__('general.password'))
                ->password()
                ->revealable()
                ->required(),
            Checkbox::make('meta.doi_crossref_test')
                ->label(__('general.use_crossref_test_api'))
                ->inline(),
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
