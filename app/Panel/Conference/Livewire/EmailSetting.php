<?php

namespace App\Panel\Conference\Livewire;

use App\Actions\MailTemplates\MailTemplateRestoreDefaultData;
use App\Actions\Settings\SettingUpdateAction;
use App\Facades\Setting;
use App\Infolists\Components\BladeEntry;
use App\Infolists\Components\VerticalTabs;
use App\Mail\Templates\TestMail;
use App\Models\MailTemplate;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;

class EmailSetting extends Component implements HasForms, HasInfolists, HasTable
{
    use InteractsWithForms;
    use InteractsWithInfolists;
    use InteractsWithTable;

    public ?array $mailSetupFormData = [];

    public ?array $layoutTemplateFormData = [];

    public function mount()
    {
        $this->layoutTemplateForm->fill(Setting::all());
    }

    public function render()
    {
        return view('panel.conference.livewire.infolist');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(MailTemplate::query())
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('subject')
                            ->searchable()
                            ->weight(FontWeight::Medium)
                            ->sortable(),
                        TextColumn::make('description')
                            ->size(TextColumnSize::Small)
                            ->searchable()
                            ->color('gray'),
                        TextColumn::make('key')
                            ->getStateUsing(fn (MailTemplate $record) => Str::afterLast($record->mailable, '\\'))
                            ->badge()
                            ->color('primary'),
                    ]),
                ]),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->color('primary')
                        ->form([
                            TextInput::make('subject')
                                ->required()
                                ->rules('required'),
                            TinyEditor::make('html_template')
                                ->label('Body')
                                ->minHeight(500)
                                ->required()
                                ->profile('email')
                                ->rules('required'),
                        ]),
                    TableAction::make('restoreDefault')
                        ->color('gray')
                        ->successNotificationTitle(__('translation.emailSetting.successNotificationTitleEmailTemplateRestored'))
                        ->icon('heroicon-o-arrow-path')
                        ->label(__('translation.emailSetting.labelRestoreDefault'))
                        ->requiresConfirmation()
                        ->failureNotificationTitle(__('translation.emailSetting.failureNotificationTitleEmailTemplateRestored'))
                        ->action(function (MailTemplate $record, TableAction $action) {

                            try {
                                MailTemplateRestoreDefaultData::run($record);
                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                $action->failure();
                            }
                        }),
                ]),
            ])
            ->bulkActions([
                // ...
            ]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                VerticalTabs\Tabs::make()
                    ->schema([
                        VerticalTabs\Tab::make(__('translation.emailSetting.verticalTabsEmailTemplates'))
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                BladeEntry::make('mail-templates')
                                    ->blade('{{ $this->table }}'),
                            ]),
                        VerticalTabs\Tab::make(__('translation.emailSetting.verticalTabsLayoutTemplates'))
                            ->icon('heroicon-o-bars-3-bottom-left')
                            ->schema([
                                BladeEntry::make('layout-templates')
                                    ->blade('{{ $this->layoutTemplateForm }}'),
                            ]),

                    ]),
            ]);
    }

    protected function getForms(): array
    {
        return [
            'layoutTemplateForm',
        ];
    }

    public function layoutTemplateForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('translation.emailSetting.headerLayoutTemplates'))
                    ->schema([
                        TinyEditor::make('mail_header')
                            ->label(__('translation.emailSetting.labelHeader'))
                            ->profile('email'),
                        TinyEditor::make('mail_footer')
                            ->label(__('translation.emailSetting.labelFooter'))
                            ->profile('email'),
                    ]),
                Actions::make([
                    Action::make('saveLayoutForm')
                        ->label(__('translation.button.save'))
                        ->successNotificationTitle(__('translation.emailSetting.successNotificationTitle'))
                        ->failureNotificationTitle(__('translation.emailSetting.failureNotificationTitle'))
                        ->action(function (Action $action) {
                            $formData = $this->layoutTemplateForm->getState();

                            try {
                                Setting::update($formData);
                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                $action->failure();
                            }
                        }),
                    Action::make('testEmail')
                        ->label(__('translation.emailSetting.labelTestEmail'))
                        ->color('gray')
                        ->successNotificationTitle(__('translation.emailSetting.successNotificationTitleSentToYourEmail'))
                        ->action(function (Action $action) {
                            try {
                                Mail::to(auth()->user()->email)->send(new TestMail);
                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                Notification::make()
                                    ->danger()
                                    ->title(__('translation.emailSetting.titleFailedToSendTestMail'))
                                    ->body($th->getMessage())
                                    ->send();
                            }
                        }),
                ])->alignLeft(),
            ])
            ->statePath('layoutTemplateFormData');
    }
}
