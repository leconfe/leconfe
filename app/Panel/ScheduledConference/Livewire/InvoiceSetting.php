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
                Section::make()
                    ->columns(1)
                    ->schema([
                        Checkbox::make('meta.invoice_enable')
                            ->label('Enable Invoice')
                            ->live(),
                        Grid::make(1)
                            ->schema([
                                Checkbox::make('meta.receipt_enable')
                                    ->label('Enable Receipt'),
                                TinyEditor::make('meta.invoice_sender_information')
                                    ->label('Sender Information'),
                                TinyEditor::make('meta.invoice_notes')
                                    ->profile('basic')
                                    ->label('Notes'),
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('meta.invoice_prefix_number')
                                            ->label('Prefix Number of Invoice'),
                                        TextInput::make('meta.invoice_number')
                                            ->numeric()
                                            ->label('Next Invoice Number'),
                                        TextInput::make('meta.invoice_suffix_number')
                                            ->label('Suffix Number of Invoice'),
                                    ]),
                            ])
                            ->visible(fn(Get $get) => $get('meta.invoice_enable')),
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
