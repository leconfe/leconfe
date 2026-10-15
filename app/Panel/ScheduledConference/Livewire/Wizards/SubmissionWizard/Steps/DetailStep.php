<?php

namespace App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Steps;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Filament\Forms\Components\MultilanguageComponent;
use App\Forms\Components\TinyEditor;
use App\Models\Submission;
use App\Models\Topic;
use App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Contracts\HasWizardStep;
use App\Support\Multilingual\ContentLocales;
use App\Utils\TinyMceWordCounter;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Livewire\Component;
use Stevebauman\Purify\Facades\Purify;

class DetailStep extends Component implements HasActions, HasForms, HasWizardStep
{
    use InteractsWithActions, InteractsWithForms;

    public Submission $record;

    public array $meta;

    public array $topic;

    public string $nextStep = 'upload-files';

    protected $listeners = ['refreshLivewire' => '$refresh'];

    /**
     * Form state paths of the metadata entered per language.
     */
    public const TRANSLATABLE_FIELDS = ['meta.title', 'meta.subtitle', 'meta.abstract', 'meta.keywords'];

    public function mount(Submission $record)
    {
        $this->form->fill($record->fillTranslatableFormState([
            'topic' => $record->topics()->pluck('id')->toArray(),
            'meta' => [
                ...$record->getAllMeta()->toArray(),
                'language' => $record->getPrimaryContentLocale(),
            ],
        ], self::TRANSLATABLE_FIELDS));
    }

    public static function getWizardLabel(): string
    {
        return __('general.details');
    }

    protected function getFormModel()
    {
        return $this->record;
    }

    public function render()
    {
        return view('panel.scheduledConference.livewire.wizards.submission-wizard.steps.detail-step');
    }

    protected function getFormSchema(): array
    {
        $abstractWordLimit = (int) ($this->record?->track?->getMeta('abstract_word_count') ?? 0);

        return [
            Section::make([
                Section::make(__('general.submission_details'))
                    ->description(__('general.provide_details_to_help_us'))
                    ->aside()
                    ->schema([
                        Hidden::make('nextStep'),
                        Select::make('topic')
                            ->visible(fn () => Topic::query()->count())
                            ->preload()
                            ->multiple()
                            ->maxItems(fn (): ?int => $this->topicSelectionLimit())
                            ->helperText(fn (): ?string => $this->topicSelectionLimitHelperText())
                            ->label(__('general.topic'))
                            ->searchable()
                            ->relationship('topics', 'name', fn ($query) => $query->with('translate'))
                            ->getOptionLabelFromRecordUsing(fn (Topic $record) => $record->translated('name')),
                        Select::make('meta.language')
                            ->label(__('general.paper_language'))
                            ->helperText(__('general.paper_language_helper'))
                            ->options(fn (): array => $this->record->getLanguageOptions())
                            ->in(fn (): array => array_keys($this->record->getLanguageOptions()))
                            ->required()
                            ->selectablePlaceholder(false)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set, ?string $old, ?string $state) => $this->moveMetadataToPaperLanguage($get, $set, $old, $state))
                            ->visible(fn (): bool => app(ContentLocales::class)->isMultilingual()),
                        MultilanguageComponent::make([
                            TextInput::make('meta.title')
                                ->label(__('general.title'))
                                ->required(),
                            TextInput::make('meta.subtitle')
                                ->label(__('general.subtitle')),
                            TagsInput::make('meta.keywords')
                                ->label(__('general.keywords'))
                                ->splitKeys([','])
                                ->placeholder(''),
                            TinyEditor::make('meta.abstract')
                                ->label(__('general.abstract'))
                                ->minHeight(300)
                                ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail) use ($abstractWordLimit) {
                                    if ($abstractWordLimit < 1 || blank($value)) {
                                        return;
                                    }

                                    if (TinyMceWordCounter::countWords($value) > $abstractWordLimit) {
                                        $fail(__('general.abstract_word_limit_exceeded', ['count' => $abstractWordLimit]));
                                    }
                                })
                                ->required(! $this->record?->track->getMeta('do_not_require_abstracts') ?? true)
                                ->dehydrateStateUsing(fn (?string $state) => Purify::clean($state)),
                        ])
                            ->primaryLocale(fn (): string => $this->paperLanguage())
                            ->locales(fn (): array => $this->formLocales()),
                    ]),
            ]),
        ];
    }

    public function nextStep()
    {
        return Action::make('nextStep')
            ->label(__('general.next'))
            ->successNotificationTitle(__('general.saved'))
            ->action(function (Action $action) {
                $this->record = SubmissionUpdateAction::run($this->form->getState(), $this->record);
                $this->dispatch('next-wizard-step');
                $action->success();
            });
    }

    private function paperLanguage(): string
    {
        $language = $this->meta['language'] ?? null;

        return array_key_exists((string) $language, $this->record->getLanguageOptions())
            ? $language
            : $this->record->getPrimaryContentLocale();
    }

    /**
     * @return array<int, string>
     */
    private function formLocales(): array
    {
        return array_values(array_unique([$this->paperLanguage(), ...$this->record->getContentLocalesForForm()]));
    }

    /**
     * Moves metadata typed for the previous paper language to the new one when the new
     * language has nothing yet, so changing the language does not lose input.
     */
    private function moveMetadataToPaperLanguage(Get $get, Set $set, ?string $old, ?string $new): void
    {
        if (! $old || ! $new || $old === $new) {
            return;
        }

        foreach (self::TRANSLATABLE_FIELDS as $field) {
            $values = $get($field);

            if (! is_array($values) || array_is_list($values)) {
                continue;
            }

            if (ContentLocales::isBlank($values[$new] ?? null) && ! ContentLocales::isBlank($values[$old] ?? null)) {
                $values[$new] = $values[$old];
                $values[$old] = is_array($values[$old]) ? [] : null;
                $set($field, $values);
            }
        }
    }

    private function topicSelectionLimit(): ?int
    {
        return $this->record
            ->scheduledConference()
            ->first()
            ?->getSubmissionTopicSelectionLimit();
    }

    private function topicSelectionLimitHelperText(): ?string
    {
        $limit = $this->topicSelectionLimit();

        return $limit === null
            ? null
            : __('general.select_up_to_topics', ['count' => $limit]);
    }
}
