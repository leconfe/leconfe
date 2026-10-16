<?php

namespace App\Panel\Conference\Livewire;

use App\Facades\Setting;
use App\Filament\Forms\Components\MultilanguageComponent;
use App\Forms\Components\TinyEditor;
use App\Infolists\Components\BladeEntry;
use App\Infolists\Components\VerticalTabs;
use App\Mail\Templates\TestMail;
use App\Models\DefaultMailTemplate;
use App\Models\MailTemplate;
use App\Support\Multilingual\ContentLocales;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
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
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

class EmailSetting extends Component implements HasForms, HasInfolists, HasTable
{
    use InteractsWithForms;
    use InteractsWithInfolists;
    use InteractsWithTable;

    public ?array $mailSetupFormData = [];

    public ?array $layoutTemplateFormData = [];

    /**
     * Layout settings and the conference meta keys that hold their translations.
     *
     * @var array<string, string>
     */
    protected const LAYOUT_TRANSLATABLE_KEYS = [
        'mail_header' => 'meta.settings_mail_header',
        'mail_footer' => 'meta.settings_mail_footer',
    ];

    public function mount()
    {
        $state = Setting::all();

        if ($conference = app()->getCurrentConference()) {
            $translations = $conference->fillTranslatableFormState([], array_values(self::LAYOUT_TRANSLATABLE_KEYS));

            foreach (self::LAYOUT_TRANSLATABLE_KEYS as $setting => $formKey) {
                if (Arr::has($translations, $formKey)) {
                    $state[$setting] = Arr::get($translations, $formKey);
                }
            }
        }

        $this->layoutTemplateForm->fill($state);
    }

    public function render()
    {
        return view('infolists.infolist');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(DefaultMailTemplate::query())
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('subject')
                            ->label(__('general.subject'))
                            ->searchable()
                            ->weight(FontWeight::Medium)
                            ->sortable(),
                        TextColumn::make('description')
                            ->label(__('general.description'))
                            ->size(TextColumnSize::Small)
                            ->searchable()
                            ->color('gray'),
                        TextColumn::make('key')
                            ->label(__('general.key'))
                            ->getStateUsing(fn (DefaultMailTemplate $record) => Str::afterLast($record->mailable, '\\'))
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
                    TableAction::make('edit')
                        ->color('primary')
                        ->icon('heroicon-m-pencil-square')
                        ->fillForm(fn (DefaultMailTemplate $record) => $this->getTemplateFormState($record))
                        ->form([
                            MultilanguageComponent::make([
                                TextInput::make('subject')
                                    ->label(__('general.subject'))
                                    ->required()
                                    ->rules('required'),
                                TinyEditor::make('html_template')
                                    ->label(__('general.body'))
                                    ->minHeight(500)
                                    ->setRelativeUrls(true)
                                    ->required()
                                    ->profile('email')
                                    ->rules('required'),
                            ]),
                        ])
                        ->action(function (DefaultMailTemplate $record, array $data) {
                            $template = MailTemplate::query()->firstOrNew([
                                'conference_id' => app()->getCurrentConference()->id,
                                'mailable' => $record->mailable,
                            ]);

                            $data = $template->syncTranslatableState(Arr::only($data, ['subject', 'html_template']));

                            $template->fill([
                                ...$data,
                                'description' => $record->description,
                                'text_template' => MailTemplate::textFromHtml($data['html_template'] ?? null),
                            ]);
                            $template->save();
                            $template->persistTranslatableState();
                        }),
                    TableAction::make('restoreDefault')
                        ->color('danger')
                        ->successNotificationTitle(__('general.email_template_restored_to_default_data'))
                        ->icon('heroicon-o-arrow-path')
                        ->label(__('general.restore_default'))
                        ->requiresConfirmation()
                        ->failureNotificationTitle(__('general.are_sure_want_restore_default_data'))
                        ->visible(fn (DefaultMailTemplate $record) => $record->custom)
                        ->action(function (DefaultMailTemplate $record, TableAction $action) {
                            try {
                                // Delete through the models so their translations are removed as well.
                                MailTemplate::query()
                                    ->where('mailable', $record->mailable)
                                    ->get()
                                    ->each
                                    ->delete();

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

    /**
     * The edit form state of a template: the conference's customized template with
     * its translations, or the built-in default in the default language.
     */
    protected function getTemplateFormState(DefaultMailTemplate $record): array
    {
        $template = MailTemplate::query()
            ->where('conference_id', app()->getCurrentConference()?->getKey())
            ->where('mailable', $record->mailable)
            ->first();

        if ($template) {
            return $template->fillTranslatableFormState(
                $template->only(['subject', 'html_template']),
                ['subject', 'html_template'],
            );
        }

        $state = [
            'subject' => $record->mailable::getDefaultSubject(),
            'html_template' => $record->mailable::getDefaultHtmlTemplate(),
        ];

        $locales = app(ContentLocales::class);

        if (! $locales->isMultilingual()) {
            return $state;
        }

        return collect($state)
            ->map(fn (string $value) => collect($locales->ordered())
                ->mapWithKeys(fn (string $locale) => [$locale => $locale === $locales->default() ? $value : null])
                ->all())
            ->all();
    }

    /**
     * Save the layout settings, with their translations kept on the conference.
     */
    protected function saveLayoutSettings(array $formData): void
    {
        $conference = app()->getCurrentConference();

        if (! $conference) {
            Setting::update($formData);

            return;
        }

        $meta = [];

        foreach (self::LAYOUT_TRANSLATABLE_KEYS as $setting => $formKey) {
            if (array_key_exists($setting, $formData)) {
                Arr::set($meta, $formKey, $formData[$setting]);
            }
        }

        $meta = $conference->syncTranslatableState($meta);

        foreach (self::LAYOUT_TRANSLATABLE_KEYS as $setting => $formKey) {
            if (Arr::has($meta, $formKey)) {
                $formData[$setting] = Arr::get($meta, $formKey);
            }
        }

        Setting::update($formData);
        $conference->persistTranslatableState();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                VerticalTabs\Tabs::make()
                    ->schema([
                        VerticalTabs\Tab::make(__('general.email_templates'))
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                BladeEntry::make('mail-templates')
                                    ->blade('{{ $this->table }}'),
                            ]),
                        VerticalTabs\Tab::make(__('general.layout_templates'))
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
                Section::make(__('general.layout_templates'))
                    ->schema([
                        MultilanguageComponent::make([
                            TinyEditor::make('mail_header')
                                ->label(__('general.header'))
                                ->profile('email'),
                            TinyEditor::make('mail_footer')
                                ->label(__('general.footer'))
                                ->profile('email'),
                        ]),
                    ]),
                Actions::make([
                    Action::make('saveLayoutForm')
                        ->label(__('general.save'))
                        ->successNotificationTitle(__('general.saved'))
                        ->failureNotificationTitle(__('general.data_could_not_saved'))
                        ->action(function (Action $action) {
                            $formData = $this->layoutTemplateForm->getState();

                            try {
                                $this->saveLayoutSettings($formData);
                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                $action->failure();
                            }
                        }),
                    Action::make('testEmail')
                        ->label(__('general.test_email'))
                        ->color('gray')
                        ->successNotificationTitle(__('general.success_sent_test_mail_to_your_email'))
                        ->action(function (Action $action) {
                            try {
                                Mail::to(auth()->user())->send(new TestMail);
                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                Notification::make()
                                    ->danger()
                                    ->title(__('general.failed_sent_test_mail_to_your_email'))
                                    ->body($th->getMessage())
                                    ->send();
                            }
                        }),
                ])->alignLeft(),
            ])
            ->statePath('layoutTemplateFormData');
    }
}
