<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Forms\Components\TinyEditor;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as ActionForm;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Livewire\Component;

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
                Section::make(__('general.invoice_document'))
                    ->columns(1)
                    ->schema([
                        Checkbox::make('meta.invoice_enable')
                            ->label(__('general.enable_invoice'))
                            ->live(),
                        Grid::make(1)
                            ->schema([
                                Checkbox::make('meta.invoice_show_submission_title')
                                    ->label(__('general.show_submission_title_on_invoice')),
                                TinyEditor::make('meta.invoice_sender_information')
                                    ->label(__('general.invoice_sender_information')),
                                TinyEditor::make('meta.invoice_notes')
                                    ->profile('basic')
                                    ->label(__('general.invoice_notes')),
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('meta.invoice_prefix_number')
                                            ->label(__('general.invoice_number_prefix')),
                                        TextInput::make('meta.invoice_number')
                                            ->numeric()
                                            ->label(__('general.next_invoice_number')),
                                        TextInput::make('meta.invoice_suffix_number')
                                            ->label(__('general.invoice_number_suffix')),
                                    ]),
                            ])
                            ->visible(fn (Get $get) => $get('meta.invoice_enable')),
                    ]),
                Section::make(__('general.receipt_document'))
                    ->columns(1)
                    ->schema([
                        Checkbox::make('meta.receipt_enable')
                            ->label(__('general.enable_receipt'))
                            ->live(),
                        Grid::make(1)
                            ->schema([
                                Checkbox::make('meta.receipt_show_submission_title')
                                    ->label(__('general.show_submission_title_on_receipt')),
                                TinyEditor::make('meta.receipt_sender_information')
                                    ->label(__('general.receipt_sender_information')),
                                TinyEditor::make('meta.receipt_notes')
                                    ->profile('basic')
                                    ->label(__('general.receipt_notes')),
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('meta.receipt_prefix_number')
                                            ->label(__('general.receipt_number_prefix')),
                                        TextInput::make('meta.receipt_number')
                                            ->numeric()
                                            ->label(__('general.next_receipt_number')),
                                        TextInput::make('meta.receipt_suffix_number')
                                            ->label(__('general.receipt_number_suffix')),
                                    ]),
                            ])
                            ->visible(fn (Get $get) => $get('meta.receipt_enable')),
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
