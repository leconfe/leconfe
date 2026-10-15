<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Forms;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Filament\Forms\Components\MultilanguageComponent;
use App\Forms\Components\TinyEditor;
use App\Models\Submission;
use App\Models\Topic;
use App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Steps\DetailStep;
use App\Support\Multilingual\ContentLocales;
use App\Utils\TinyMceWordCounter;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Stevebauman\Purify\Facades\Purify;

class Detail extends \Livewire\Component implements HasForms
{
    use InteractsWithForms;

    public Submission $submission;

    public array $meta = [];

    public array $topics = [];

    public function mount(Submission $submission)
    {
        $this->form->fill($this->submission->fillTranslatableFormState([
            'topics' => $this->submission->topics()->pluck('id')->toArray(),
            'meta' => [
                ...$this->submission->getAllMeta()->toArray(),
                'language' => $this->submission->getPrimaryContentLocale(),
            ],
        ], DetailStep::TRANSLATABLE_FIELDS));
    }

    public function form(Form $form): Form
    {
        $abstractWordLimit = (int) ($this->submission?->track?->getMeta('abstract_word_count') ?? 0);

        return $form
            ->disabled(function (): bool {
                return ! auth()->user()->can('editing', $this->submission);
            })
            ->model($this->submission)
            ->schema([
                Toggle::make('meta.paper_published_on_external')
                    ->label(__('general.paper_published_on_external'))
                    ->reactive(),
                TextInput::make('meta.paper_external_url')
                    ->label(__('general.paper_external_url'))
                    ->visible(fn (Get $get) => $get('meta.paper_published_on_external'))
                    ->url()
                    ->required()
                    ->placeholder('https://'),
                Select::make('meta.language')
                    ->label(__('general.paper_language'))
                    ->helperText(__('general.paper_language_helper'))
                    ->options(fn (): array => $this->submission->getLanguageOptions())
                    ->in(fn (): array => array_keys($this->submission->getLanguageOptions()))
                    ->required()
                    ->selectablePlaceholder(false)
                    ->live()
                    ->visible(fn (): bool => app(ContentLocales::class)->isMultilingual()),
                MultilanguageComponent::make([
                    TextInput::make('meta.title')
                        ->label(__('general.title'))
                        ->required(),
                    TextInput::make('meta.subtitle')
                        ->label(__('general.subtitle')),
                ])
                    ->primaryLocale(fn (): string => $this->paperLanguage())
                    ->locales(fn (): array => $this->formLocales()),
                Select::make('topics')
                    ->preload()
                    ->multiple()
                    ->maxItems(fn (): ?int => $this->topicSelectionLimit())
                    ->helperText(fn (): ?string => $this->topicSelectionLimitHelperText())
                    ->relationship('topics', 'name', fn ($query) => $query->with('translate'))
                    ->getOptionLabelFromRecordUsing(fn (Topic $record) => $record->translated('name'))
                    ->label(__('general.topic'))
                    ->searchable(),
                MultilanguageComponent::make([
                    TagsInput::make('meta.keywords')
                        ->label(__('general.keywords'))
                        ->splitKeys([','])
                        ->placeholder(''),
                    TinyEditor::make('meta.abstract')
                        ->label(__('general.abstract'))
                        ->required()
                        ->minHeight(300)
                        ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail) use ($abstractWordLimit) {
                            if ($abstractWordLimit < 1 || blank($value)) {
                                return;
                            }

                            if (TinyMceWordCounter::countWords($value) > $abstractWordLimit) {
                                $fail(__('general.abstract_word_limit_exceeded', ['count' => $abstractWordLimit]));
                            }
                        })
                        ->dehydrateStateUsing(fn (?string $state) => Purify::clean($state)),
                ])
                    ->primaryLocale(fn (): string => $this->paperLanguage())
                    ->locales(fn (): array => $this->formLocales()),
            ]);
    }

    public function submit(): void
    {

        SubmissionUpdateAction::run(
            $this->form->getState(),
            $this->submission
        );

        Notification::make()
            ->body(__('general.saved_successfuly'))
            ->success()
            ->send();
    }

    public function render()
    {
        return view('panel.scheduledConference.livewire.submissions.forms.detail');
    }

    private function paperLanguage(): string
    {
        $language = $this->meta['language'] ?? null;

        return array_key_exists((string) $language, $this->submission->getLanguageOptions())
            ? $language
            : $this->submission->getPrimaryContentLocale();
    }

    /**
     * @return array<int, string>
     */
    private function formLocales(): array
    {
        return array_values(array_unique([$this->paperLanguage(), ...$this->submission->getContentLocalesForForm()]));
    }

    private function topicSelectionLimit(): ?int
    {
        return $this->submission
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
