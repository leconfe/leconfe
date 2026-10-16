<?php

namespace App\Panel\ScheduledConference\Resources\SubmissionResource\Pages;

use App\Actions\Submissions\SubmissionCreateAction;
use App\Facades\Hook;
use App\Filament\Forms\Components\MultilanguageComponent;
use App\Frontend\ScheduledConference\Pages\Submissions;
use App\Managers\PaymentManager;
use App\Models\Enums\UserRole;
use App\Models\PaymentFee;
use App\Models\PaymentFormItem;
use App\Models\Role;
use App\Models\Submission;
use App\Models\SubmissionFormItem;
use App\Models\Timeline;
use App\Models\Track;
use App\Models\User;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use App\Support\Multilingual\ContentLocales;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class CreateSubmission extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Make a Submission';

    protected static string $resource = SubmissionResource::class;

    protected static string $view = 'panel.conference.resources.submission-resource.pages.create-submission';

    public $data;

    public function mount(): void
    {
        $this->form->fill([
            'meta' => ['language' => app(ContentLocales::class)->default()],
        ]);
    }

    /**
     * The chosen paper language when it is enabled, else the site default language.
     */
    protected function paperLanguage(): string
    {
        $language = data_get($this->data, 'meta.language');
        $contentLocales = app(ContentLocales::class);

        return $contentLocales->isEnabled($language) ? $language : $contentLocales->default();
    }

    /**
     * Moves a title typed for the previous paper language to the new one when the new
     * language has no title yet, so changing the language does not lose input.
     */
    protected function moveTitleToPaperLanguage(Get $get, Set $set, ?string $old, ?string $new): void
    {
        if (! $old || ! $new || $old === $new) {
            return;
        }

        $titles = $get('meta.title');

        if (! is_array($titles) || array_is_list($titles)) {
            return;
        }

        if (ContentLocales::isBlank($titles[$new] ?? null) && ! ContentLocales::isBlank($titles[$old] ?? null)) {
            $titles[$new] = $titles[$old];
            $titles[$old] = null;
            $set('meta.title', $titles);
        }
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    /**
     * Whether the current scheduled conference accepts submissions from the user: the
     * submission timeline is open and at least one active track is available to them.
     */
    public static function isAcceptingSubmissions(?User $user = null): bool
    {
        $isEditor = $user?->hasAnyRole([
            UserRole::Admin,
            UserRole::ConferenceManager,
            UserRole::ScheduledConferenceEditor,
            UserRole::TrackEditor,
        ]) ?? false;

        return Timeline::isSubmissionOpen() && Track::query()
            ->active()
            ->when(! $isEditor, fn ($query) => $query->whereMeta('submit_only_for_editors', false))
            ->exists();
    }

    protected function getViewData(): array
    {
        $isOpen = static::isAcceptingSubmissions(auth()->user());
        $closedMessage = __('general.not_accepting_submissions');

        Hook::call('Panel::ScheduledConference::SubmissionCreate::availability', [&$isOpen, &$closedMessage, $this]);

        return [
            'isOpen' => (bool) $isOpen,
            'closedMessage' => $closedMessage,
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Placeholder::make('before_you_begin')
                    ->label(__('general.before_you_begin'))
                    ->extraAttributes(['class' => 'prose prose-sm max-w-none'])
                    ->visible(fn () => ! ContentLocales::isBlank(app()->getCurrentScheduledConference()->translated('before_you_begin')))
                    ->content(fn () => new HtmlString(app()->getCurrentScheduledConference()->translated('before_you_begin'))),
                Placeholder::make('author_guidelines_link')
                    ->hiddenLabel()
                    ->visible(fn () => ! ContentLocales::isBlank(app()->getCurrentScheduledConference()->translated('author_guidelines')))
                    ->content(fn () => new HtmlString(sprintf(
                        '<a href="%s" target="_blank" rel="noopener" class="text-primary-600 hover:underline">%s</a>',
                        e(route(Submissions::getRouteName('scheduledConference'))),
                        e(__('general.read_author_guidelines')),
                    ))),
                Select::make('meta.language')
                    ->label(__('general.paper_language'))
                    ->helperText(__('general.paper_language_helper'))
                    ->options(fn (): array => app(ContentLocales::class)->options())
                    ->in(fn (): array => array_keys(app(ContentLocales::class)->options()))
                    ->required()
                    ->selectablePlaceholder(false)
                    ->default(fn (): string => app(ContentLocales::class)->default())
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set, ?string $old, ?string $state) => $this->moveTitleToPaperLanguage($get, $set, $old, $state))
                    ->visible(fn (): bool => app(ContentLocales::class)->isMultilingual()),
                MultilanguageComponent::make([
                    TextInput::make('meta.title')
                        ->label(__('general.title'))
                        ->required(),
                ])
                    ->primaryLocale(fn (): string => $this->paperLanguage())
                    ->locales(fn (): array => [$this->paperLanguage(), ...app(ContentLocales::class)->ordered()]),
                Grid::make(1)
                    ->visible(SubmissionFormItem::exists())
                    ->schema([
                        ...SubmissionFormItem::buildFormSchema(),
                    ]),
                Grid::make(1)
                    ->visible(fn () => app()->getCurrentScheduledConference()->getMeta('submission_payment'))
                    ->schema([
                        Radio::make('payment_fee_id')
                            ->label('Payment Fee')
                            ->required()
                            ->live()
                            ->options(
                                fn () => PaymentFee::type(PaymentManager::TYPE_SUBMISSION_FEE)
                                    ->active()
                                    ->with('translate')
                                    ->get()
                                    ->mapWithKeys(fn (PaymentFee $paymentFee) => [$paymentFee->getKey() => $paymentFee->translated('name')])
                            )
                            ->descriptions(
                                fn () => PaymentFee::type(PaymentManager::TYPE_SUBMISSION_FEE)
                                    ->active()
                                    ->get()
                                    ->mapWithKeys(fn (PaymentFee $paymentFee) => [$paymentFee->getKey() => '('.$paymentFee->getFormattedFee().')'])
                            ),
                        \Filament\Forms\Components\Fieldset::make('Add-on Items')
                            ->schema(function (Get $get) {
                                $paymentFee = PaymentFee::find($get('payment_fee_id'));
                                if (! $paymentFee) {
                                    return [];
                                }

                                return collect($paymentFee->getTranslatedAdditionalItems())->map(function ($item) use ($paymentFee) {
                                    $formattedAmount = money($item['amount'], $paymentFee->currency, true)->formatWithoutZeroes();

                                    return \App\Forms\Components\AddOnItemCounter::make("additional_items.{$item['key']}")
                                        ->label("{$item['name']} ({$formattedAmount})")
                                        ->helperText($item['description'] ?? null)
                                        ->minValue(0)
                                        ->maxValue(999);
                                })->toArray();
                            })
                            ->columns(1)
                            ->visible(function (Get $get) {
                                $paymentFee = PaymentFee::find($get('payment_fee_id'));

                                return $paymentFee && count($paymentFee->getAdditionalItems()) > 0;
                            }),
                        ...PaymentFormItem::buildFormSchema(PaymentManager::TYPE_SUBMISSION_FEE),
                    ]),
                Radio::make('track_id')
                    ->label(__('general.track'))
                    ->required()
                    ->visible(fn ($component) => count($component->getOptions()) > 1)
                    ->options(
                        fn () => Track::query()
                            ->active()
                            ->whereMeta('submit_only_for_editors', auth()->user()->hasRole([
                                UserRole::Admin,
                                UserRole::ConferenceManager,
                                UserRole::ScheduledConferenceEditor,
                                UserRole::TrackEditor,
                            ]))
                            ->with('translate')
                            ->get()
                            ->mapWithKeys(fn (Track $track) => [$track->getKey() => $track->translated('title')])
                    )
                    ->reactive(),
                Placeholder::make('track_policy')
                    ->extraAttributes(['class' => 'prose prose-sm max-w-none'])
                    ->visible(function (Get $get) {
                        if (! $get('track_id')) {
                            return false;
                        }

                        $track = Track::find($get('track_id'));

                        return $track && ! ContentLocales::isBlank($track->translated('policy'));
                    })
                    ->label(function (Get $get) {
                        if (! $get('track_id')) {
                            return '';
                        }

                        return Track::find($get('track_id'))?->translated('title') ?? '';
                    })
                    ->content(fn (Get $get) => $get('track_id') ? new HtmlString(Track::find($get('track_id'))?->translated('policy') ?? '') : ''),
                Fieldset::make(__('general.submission_checklist'))
                    ->columns(1)
                    ->schema([
                        Placeholder::make('submission_checklist')
                            ->hiddenLabel()
                            ->extraAttributes(['class' => 'prose prose-sm'])
                            ->visible(fn () => ! ContentLocales::isBlank(app()->getCurrentScheduledConference()->translated('submission_checklist')))
                            ->content(fn () => new HtmlString(app()->getCurrentScheduledConference()->translated('submission_checklist'))),
                        Checkbox::make('submissionRequirements')
                            ->required()
                            ->label(__('general.submission_meets_all_of_requirements')),
                    ]),
                Section::make(__('general.privacy_consent'))
                    ->schema([
                        Checkbox::make('privacy_consent')
                            ->inline()
                            ->required()
                            ->label(__('general.agree_to_my_collected_stored_to_privacy_statement')),
                    ]),
                Radio::make('user_group_id')
                    ->label(__('general.submit_as'))
                    ->hidden(fn ($component) => count($component->getOptions()) < 2 || ! auth()->user()->can('submitAs', Submission::class))
                    ->options(function () {
                        $managerUserGroupAssignments = Role::query()->get()->filter(fn ($role) => auth()->user()->hasRole($role) && $role->hasDefaultPermission('Submission:submitAs'));
                        $authorUserGroupAssignments = Role::query()->where('name', UserRole::Author)->get();

                        return $managerUserGroupAssignments->merge($authorUserGroupAssignments)->pluck('name', 'id');
                    }),
            ])
            ->model(Submission::class)
            ->statePath('data');
    }

    public function submit()
    {
        $data = $this->form->getState();
        $submissionFormResponses = SubmissionFormItem::filterOutUploadResponses(data_get($data, 'submission_form_responses'));
        $paymentFormResponses = PaymentFormItem::filterOutUploadResponses(data_get($data, 'form_responses'));

        if (! auth()->user()->hasRole(UserRole::Author)) {
            auth()->user()->assignRole(UserRole::Author);
        }

        try {
            DB::beginTransaction();

            $submission = SubmissionCreateAction::run($data);

            $submitAsRole = Role::query()
                ->when(
                    array_key_exists('user_group_id', $data),
                    fn ($query) => $query->where('id', $data['user_group_id']),
                    fn ($query) => $query->where('name', UserRole::Author)
                )
                ->first();

            if ($submitAsRole === null) {
                throw new \Exception('Role not found');
            }

            if (! auth()->user()->hasRole($submitAsRole)) {
                throw new \Exception('You are not allowed to submit as this role');
            }

            $submission->participants()->create([
                'user_id' => $submission->user_id,
                'role_id' => $submitAsRole->getKey(),
            ]);

            if (array_key_exists('submission_form_responses', $data)) {
                $submission->setMeta('submission_form_responses', $submissionFormResponses);
            }

            $this->form->model($submission)->saveRelationships();

            if ($paymentFeeId = data_get($data, 'payment_fee_id')) {
                $paymentManager = PaymentManager::get();

                $paymentFee = PaymentFee::find($paymentFeeId);
                $additionalItems = data_get($data, 'additional_items', []);
                $selectedAdditionalItems = $paymentFee->getSelectedAdditionalItemsFromData(['additional_items' => $additionalItems]);
                $totalAmount = $paymentFee->getAmountWithAdditionalItemsFromData(['additional_items' => $additionalItems]);

                $payment = $paymentManager->queue(
                    $submission,
                    $paymentFee,
                    $submission->user,
                    PaymentManager::TYPE_SUBMISSION_FEE,
                    $submission->translated('title', $submission->getPrimaryContentLocale()),
                    SubmissionResource::getUrl('view', ['record' => $submission]),
                    $paymentFee->getMeta('description'),
                    $totalAmount,
                    $paymentFee->currency,
                    null,
                    $selectedAdditionalItems,
                    $paymentFee->amount,
                );

                if (array_key_exists('form_responses', $data)) {
                    $payment->setMeta('form_responses', $paymentFormResponses);
                }

                $this->form->model($payment)->saveRelationships();
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            Notification::make()
                ->title($th->getMessage())
                ->danger()
                ->send();

            return;
        }

        return redirect()->to(SubmissionResource::getUrl('view', [$submission->id]));
    }
}
