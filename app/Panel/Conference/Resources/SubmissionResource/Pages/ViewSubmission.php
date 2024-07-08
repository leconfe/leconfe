<?php

namespace App\Panel\Conference\Resources\SubmissionResource\Pages;

use App\Classes\Log;
use App\Models\User;
use App\Models\Payment;
use Filament\Forms\Get;
use Filament\Forms\Form;
use App\Models\PaymentItem;
use Illuminate\Support\Arr;
use Squire\Models\Currency;
use App\Models\MailTemplate;
use Filament\Actions\Action;
use App\Models\Enums\UserRole;
use Filament\Infolists\Infolist;
use App\Notifications\NewPayment;
use App\Models\Enums\PaymentState;
use App\Notifications\PaymentSent;
use Filament\Actions\StaticAction;
use Filament\Resources\Pages\Page;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use App\Models\Enums\SubmissionStage;
use Filament\Forms\Components\Select;
use App\Models\Enums\SubmissionStatus;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Textarea;
use Awcodes\Shout\Components\ShoutEntry;
use Filament\Forms\Components\TextInput;
use App\Facades\Payment as FacadesPayment;
use App\Notifications\SubmissionWithdrawn;
use Illuminate\Contracts\Support\Htmlable;
use App\Infolists\Components\LivewireEntry;
use App\Notifications\PaymentStatusUpdated;
use Filament\Forms\Components\CheckboxList;
use Illuminate\View\Compilers\BladeCompiler;
use App\Mail\Templates\PublishSubmissionMail;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Forms\Concerns\InteractsWithForms;
use App\Notifications\SubmissionWithdrawRequested;
use App\Actions\Submissions\AcceptWithdrawalAction;
use App\Actions\Submissions\CancelWithdrawalAction;
use App\Actions\Submissions\RequestWithdrawalAction;
use App\Infolists\Components\VerticalTabs\Tab as Tab;
use App\Panel\Conference\Livewire\Submissions\Editing;
use App\Panel\Conference\Resources\SubmissionResource;
use App\Infolists\Components\VerticalTabs\Tabs as Tabs;
use App\Models\Enums\PresenterStatus;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use App\Panel\Conference\Livewire\Submissions\PeerReview;
use Filament\Infolists\Components\Tabs as HorizontalTabs;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use App\Panel\Conference\Livewire\Submissions\Forms\Detail;
use Filament\Infolists\Components\Tabs\Tab as HorizontalTab;
use App\Panel\Conference\Livewire\Submissions\CallforAbstract;
use App\Panel\Conference\Livewire\Submissions\Forms\References;
use App\Panel\Conference\Livewire\Workflows\Classes\StageManager;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;
use App\Panel\Conference\Livewire\Submissions\Components\GalleyList;
use App\Panel\Conference\Livewire\Submissions\Components\PresenterList;
use App\Panel\Conference\Livewire\Workflows\Concerns\InteractWithTenant;
use App\Panel\Conference\Livewire\Submissions\Components\ActivityLogList;
use App\Panel\Conference\Livewire\Submissions\Components\ContributorList;
use App\Panel\Conference\Livewire\Submissions\Components\Files\PresenterFiles;
use App\Panel\Conference\Livewire\Submissions\Components\SubmissionProceeding;
use Filament\Support\Enums\MaxWidth;

class ViewSubmission extends Page implements HasForms, HasInfolists
{
    use InteractsWithForms, InteractsWithInfolists, InteractsWithRecord, InteractWithTenant;

    protected static string $resource = SubmissionResource::class;

    protected static string $view = 'panel.conference.resources.submission-resource.pages.view-submission';

    public function mount($record): void
    {
        static::authorizeResourceAccess();

        $this->record = $this->resolveRecord($record);

        abort_unless(static::getResource()::canView($this->getRecord()), 403);
    }

    /**
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        $resource = static::getResource();

        $breadcrumb = $this->getBreadcrumb();

        return [
            $resource::getUrl() => $resource::getBreadcrumb(),
            ...(filled($breadcrumb) ? [$breadcrumb] : []),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('payment')
                ->visible($this->record->hasPaymentProcess())
                ->record(fn () => $this->record->payment)
                ->model(Payment::class)
                ->icon('heroicon-o-currency-dollar')
                ->color('primary')
                ->modalHeading(__('translation.submissions.viewSubMissionmodalHeading'))
                ->when(
                    fn (Action $action) => ! FacadesPayment::driver($action->getRecord()?->payment_method),
                    fn (Action $action) => $action
                        ->modalContent(function ($action) {
                            $paymentMethod = $action->getRecord()?->payment_method ?? FacadesPayment::getDefaultDriver();
                        
                            $modalContent = __('translation.submissions.viewSubMissionmodalContent') . '<br>' . __('translation.submissions.viewSubMissionmodalContentPayment') . $paymentMethod;
                        
                            return new HtmlString("<p>{$modalContent}</p>");
                        })
                    
                        ->modalWidth('xl')
                        ->modalSubmitAction(false),
                )
                ->when(
                    fn (Action $action): bool => FacadesPayment::driver() && (! $action->getRecord() || $action->getRecord()?->state->isOneOf(PaymentState::Unpaid)),
                    fn (Action $action): Action => $action
                        ->action(function (Action $action, array $data, Form $form) {

                            $payment = FacadesPayment::createPayment(
                                $this->record,
                                auth()->user(),
                                $data['currency_id'],
                                $data['items'],
                            );

                            $form->model($payment)->saveRelationships();

                            $paymentDriver = FacadesPayment::driver($payment?->payment_method);

                            $paymentDriver->handlePayment($payment);

                            $items = Arr::join($payment->getMeta('items'), ', ');

                            Log::make($this->record, 'submission', "Payment for {$items} has been made.")
                                ->by(auth()->user())
                                ->save();

                            try {
                                $this->record->user->notify(
                                    new PaymentSent($this->record)
                                );
                                $this->record->getEditors()->each(
                                    function (User $editor) {
                                        $editor->notify(new NewPayment($this->record));
                                    }
                                );
                                User::role([UserRole::Admin->value, UserRole::ConferenceManager->value])
                                    ->lazy()
                                    ->each(fn ($user) => $user->notify(new NewPayment($this->record)));
                            } catch (\Exception $e) {
                                $action->failureNotificationTitle(__('translation.submissions.viewSubMissionFailureNotificationTitlesend'));
                                $action->failure();
                            }
                            $action->successNotificationTitle(__('translation.submissions.viewSubMissionsuccessNotificationTitlesend'));
                            $action->success();
                        })->mountUsing(function (Form $form, ?Payment $record) {

                            $paymentDriver = FacadesPayment::driver($record?->payment_method);
                            $form->fill([
                                'currency_id' => $record?->currency_id,
                                ...$paymentDriver->getPaymentFormFill(),
                            ]);
                        })->form(function (?Payment $record) {

                            $paymentDriver = FacadesPayment::driver($record?->payment_method);

                            return [
                                Select::make('currency_id')
                                    ->label(__('translation.submissions.viewSubMissionLabelCurrency'))
                                    ->options(
                                        Currency::query()
                                            ->whereIn('id', App::getCurrentConference()->getSupportedCurrencies())
                                            ->get()
                                            ->mapWithKeys(fn (Currency $currency) => [$currency->id => $currency->name.' ('.$currency->symbol_native.')'])
                                    )
                                    ->required()
                                    ->reactive(),
                                CheckboxList::make('items')
                                    ->visible(fn (Get $get) => $get('currency_id'))
                                    ->required()
                                    ->options(function (Get $get) {
                                        return PaymentItem::get()
                                            ->filter(function (PaymentItem $item) use ($get): bool {
                                                foreach ($item->fees as $fee) {
                                                    if (! array_key_exists('currency_id', $fee)) {
                                                        continue;
                                                    }
                                                    if ($fee['currency_id'] === $get('currency_id')) {
                                                        return true;
                                                    }
                                                }

                                                return false;
                                            })
                                            ->mapWithKeys(fn (PaymentItem $item): array => [$item->id => $item->name.': '.$item->getFormattedAmount($get('currency_id'))]);
                                    }),
                                ...$paymentDriver->getPaymentFormSchema() ?? [],
                            ];
                        }),
                )
                ->when(
                    fn (Action $action): bool => FacadesPayment::driver($action->getRecord()?->payment_method) && $action->getRecord()?->state->isOneOf(PaymentState::Processing, PaymentState::Paid, PaymentState::Waived),
                    fn (Action $action): Action => $action
                        ->action(function (array $data, $record) use ($action) {
                            $record->state = $data['decision'];
                            $record->save();
                            try {
                                $record->user->notify(
                                    new PaymentStatusUpdated($record)
                                );
                            } catch (\Exception $e) {
                                $action->failureNotificationTitle(__('translation.submissions.viewSubMissionFailureNotificationTitlesend'));
                                $action->failure();
                            }
                            $action->success();
                        })
                        ->modalSubmitAction(fn (StaticAction $action, ?Payment $record) => $action->visible(auth()->user()->can('update', $record)))
                        ->modalCancelAction(fn (StaticAction $action, ?Payment $record) => $action->visible(auth()->user()->can('update', $record)))
                        ->mountUsing(function (Form $form) {
                            $payment = $this->record->payment;

                            $form->fill([
                                'currency_id' => $payment?->currency_id,
                                'amount' => $payment?->amount,
                                'items' => array_keys($payment?->getMeta('items') ?? []),
                                ...FacadesPayment::driver($payment?->payment_method)?->getPaymentFormFill() ?? [],
                            ]);

                            $form->disabled(fn ($record) => ! auth()->user()->can('update', $record));
                        })
                        ->form([
                            Grid::make(1)
                                ->schema([
                                    Grid::make()
                                        ->schema([
                                            Select::make('currency_id')
                                                ->label(__('translation.submissions.viewSubMissionLabelCurrency'))
                                                ->options(Currency::pluck('name', 'id')),
                                            TextInput::make('amount')
                                                ->prefix(fn (Get $get) => $get('currency_id') ? Currency::find($get('currency_id'))->symbol_native : null)
                                                ->numeric(),
                                        ]),
                                    CheckboxList::make('items')
                                        ->options($this->record->payment?->getMeta('items')),

                                    ...FacadesPayment::driver($this->record->payment?->payment_method)?->getPaymentFormSchema() ?? [],
                                ])
                                ->disabled(),
                            Select::make('decision')
                                ->required()
                                ->visible(fn ($record) => auth()->user()->can('update', $record))
                                ->options([
                                    PaymentState::Unpaid->value => PaymentState::Unpaid->name,
                                    PaymentState::Waived->value => PaymentState::Waived->name,
                                    PaymentState::Paid->value => PaymentState::Paid->name,
                                ]),
                        ])
                ),
            Action::make('view')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->outlined()
                ->url(route('livewirePageGroup.conference.pages.submission-detail', ['submission' => $this->record->id]), true)
                ->label(function () {
                    if ($this->record->isPublished()) {
                        return 'View';
                    }

                    if (StageManager::editing()->isStageOpen() && auth()->user()->can('editing', $this->record)) {
                        return 'Preview';
                    }
                })
                ->visible(
                    fn (): bool => ($this->record->isPublished() 
                        || (StageManager::editing()->isStageOpen() && auth()->user()->can('editing', $this->record)))
                        && $this->record->proceeding 
                ),
            Action::make('assign_proceeding')
                ->label(__('translation.submissions.viewSubMissionLabelPublishNow'))
                ->modalHeading(__('translation.submissions.viewSubMissionLabelmodalHeadingAssign'))
                ->disabled(! StageManager::editing()->isStageOpen())
                ->visible(fn () => ! $this->record->proceeding && $this->record->stage == SubmissionStage::Editing)
                ->modalWidth(MaxWidth::ExtraLarge)
                ->form(SubmissionProceeding::getFormAssignProceeding($this->record))
                ->action(function (array $data) {
                    SubmissionProceeding::assignProceeding($this->record, $data);

                    $this->replaceMountedAction('publish');
                    $this->dispatch('refreshSubmissionProceeding');
                }),
            Action::make('publish')
                ->color('primary')
                ->label(__('translation.submissions.viewSubMissionLabelPublishNow'))
                ->disabled(! StageManager::editing()->isStageOpen())
                ->visible(
                    fn (): bool => $this->record->proceeding ? true : false
                )
                ->authorize('publish', $this->record)
                ->when(
                    fn () => $this->record->hasPaymentProcess() && ! $this->record->payment?->isCompleted(),
                    fn (Action $action): Action => $action
                    ->modalContent(new HtmlString(<<<'HTML'
                            <p>{{ __('translation.submissions.viewSubMissionModalContentFee') }}</p>
                        HTML))
                        ->modalWidth('xl')
                        ->modalSubmitAction(false)
                )
                ->when(
                    fn () => ! $this->record->hasPaymentProcess() || $this->record->payment?->isCompleted(),
                    fn (Action $action): Action => $action
                        ->successNotificationTitle(__('translation.submissions.viewSubMissionsuccessNotificationTitlePublished'))
                        ->mountUsing(function (Form $form) {
                            $mailTemplate = MailTemplate::where('mailable', PublishSubmissionMail::class)->first();
                            $form->fill([
                                'email' => $this->record->user->email,
                                'subject' => $mailTemplate ? $mailTemplate->subject : '',
                                'message' => $mailTemplate ? $mailTemplate->html_template : '',
                            ]);
                        })
                        ->form([
                            Fieldset::make('Notification')
                                ->columns(1)
                                ->schema([
                                    TextInput::make('email')
                                        ->disabled()
                                        ->dehydrated(),
                                    TextInput::make('subject')
                                        ->required(),
                                    TinyEditor::make('message')
                                        ->minHeight(300),
                                    Checkbox::make('do-not-notify-author')
                                        ->label(__('translation.submissions.viewSubMissionLabelDontSend')),
                                ]),
                        ])
                        ->action(function (Action $action, array $data) {
                            $checkedPresenters = $this->record->presenters()?->whereStatus(PresenterStatus::Unchecked)->exists();

                            if ($checkedPresenters) {
                                $action->failureNotificationTitle(__('translation.submissions.viewSubMissionfailureNotificationTitleChecked'));
                                $action->failure();

                                return false;
                            }

                            $this->record->state()->publish();

                            if (! $data['do-not-notify-author']) {
                                try {
                                    Mail::to($this->record->user->email)
                                        ->send(
                                            (new PublishSubmissionMail($this->record))
                                                ->subjectUsing($data['subject'])
                                                ->contentUsing($data['message'])
                                        );
                                } catch (\Exception $e) {
                                    $action->failureNotificationTitle(__('translation.submissions.viewSubMissionfailureNotificationTitleToAuthor'));
                                    $action->failure();
                                }
                            }
                            $action->successRedirectUrl(
                                SubmissionResource::getUrl('view', [
                                    'record' => $this->record->getKey(),
                                ])
                            );
                            $action->success();
                        })
                ),
            Action::make('unpublish')
                ->icon('lineawesome-calendar-times-solid')
                ->color('danger')
                ->authorize('unpublish', $this->record)
                ->requiresConfirmation()
                ->successNotificationTitle(__('translation.submissions.viewSubMissionsuccessNotificationTitleUnpblished'))
                ->action(function (Action $action) {
                    $this->record->state()->unpublish();

                    $action->successRedirectUrl(
                        static::getResource()::getUrl('view', [
                            'record' => $this->record,
                        ])
                    );

                    $action->success();
                }),
            Action::make('request_withdraw')
                ->outlined()
                ->color('danger')
                ->authorize('requestWithdraw', $this->record)
                ->label(__('translation.submissions.viewSubMissionLabelRequestForWithdrawal'))
                ->icon('lineawesome-times-circle-solid')
                ->form([
                    Textarea::make('reason')
                        ->required()
                        ->placeholder(__('translation.submissions.viewSubMissionPlaceholderReasonForWithdrawal'))
                        ->label(__('translation.submissions.viewSubMissionLabelReason')),
                ])
                ->requiresConfirmation()
                ->successNotificationTitle(__('translation.submissions.viewSubMissionsuccessNotificationTitleWithdrawal'))
                ->action(function (Action $action, array $data) {
                    RequestWithdrawalAction::run(
                        $this->record,
                        $data['reason']
                    );

                    try {
                        // Currently using admin, next is admin removed only managers
                        User::whereHas(
                            'roles',
                            fn ($query) => $query->whereIn('name', [UserRole::Admin->value, UserRole::ConferenceManager->value])
                        )
                            ->get()
                            ->each(
                                fn ($manager) => $manager->notify(new SubmissionWithdrawRequested($this->record))
                            );

                        $this
                            ->record
                            ->getEditors()
                            ->each(function (User $editor) {
                                $editor->notify(new SubmissionWithdrawRequested($this->record));
                            });
                    } catch (\Exception $e) {
                        $action->failureNotificationTitle(__('translation.submissions.viewSubMissionFailureNotificationTitlesend'));
                        $action->failure();
                    }

                    $action->successRedirectUrl(
                        SubmissionResource::getUrl('view', [
                            'record' => $this->record,
                        ]),
                    );
                    $action->success();
                })
                ->modalWidth('xl'),
            Action::make('withdraw')
                ->outlined()
                ->color('danger')
                ->extraAttributes(function (Action $action) {
                    if (filled($this->record->withdrawn_reason)) {
                        $attributeValue = '$nextTick(() => { $wire.mountAction(\''.$action->getName().'\') })';

                        return [
                            'x-init' => new HtmlString($attributeValue),
                        ];
                    }

                    return [];
                })
                ->authorize('withdraw', $this->record)
                ->mountUsing(function (Form $form) {
                    $form->fill([
                        'reason' => $this->record->withdrawn_reason,
                    ]);
                })
                ->form([
                    Textarea::make('reason')
                        ->readonly()
                        ->hint(__('translation.submissions.viewSubMissionReasonReadOnly'))
                        ->placeholder(__('translation.submissions.viewSubMissionReasonplaceholder'))
                        ->label(__('translation.submissions.viewSubMissionLabelReason')),
                ])
                ->requiresConfirmation()
                ->modalHeading(function () {
                    return $this->record->user->fullName. __('translation.submissions.viewSubMissionModalHeadingRequested');
                })
                ->modalDescription(__('translation.submissions.viewSubMissionmodalDescriptionYouCanEither'))
                ->modalCancelActionLabel(__('translation.submissions.viewSubMissionmodalmodalCancelActionLabel'))
                ->modalSubmitActionLabel(__('translation.submissions.viewSubMissionmodalSubmitActionLabel'))
                ->successNotificationTitle(__('translation.submissions.viewSubMissionsuccessNotificationTitle'))
                ->extraModalFooterActions([
                    Action::make('reject')
                        ->color('warning')
                        ->outlined()
                        ->action(function (Action $action) {
                            CancelWithdrawalAction::run($this->record);
                            $action->successRedirectUrl(
                                SubmissionResource::getUrl('view', [
                                    'record' => $this->record,
                                ]),
                            );
                            $action->successNotificationTitle(__('translation.submissions.viewSubMissionsuccessNotificationTitleWithdrawalRequest'));
                            $action->success();
                        }),
                ])
                ->action(function (Action $action) {
                    AcceptWithdrawalAction::run($this->record);
                    try {
                        $this->record->user->notify(
                            new SubmissionWithdrawn($this->record)
                        );
                    } catch (\Exception $e) {
                        $action->failureNotificationTitle(__('translation.submissions.viewSubMissionFailureNotificationTitlesend'));
                        $action->failure();
                    }
                    $action->successRedirectUrl(
                        SubmissionResource::getUrl('view', [
                            'record' => $this->record,
                        ]),
                    );
                    $action->success();
                })
                ->modalWidth('2xl'),
            Action::make('activity-log')
                ->label(__('translation.submissions.activityLogListLabelActivityLog'))
                ->hidden(
                    fn (): bool => $this->record->stage == SubmissionStage::Wizard
                )
                ->outlined()
                ->icon('lineawesome-history-solid')
                ->modalHeading(__('translation.submissions.viewSubMissionModalHeadingActivityLog'))
                ->modalDescription(__('translation.submissions.viewSubMissionModalDescriptionActivityLog'))
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('translation.button.close'))
                ->infolist(function () {
                    return [
                        LivewireEntry::make('activites-table')
                            ->livewire(ActivityLogList::class, [
                                'submission' => $this->record,
                                'lazy' => true,
                            ]),
                    ];
                }),
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        $badgeHtml = '<div class="flex items-center gap-x-2">';

        $badgeHtml .= match ($this->record->status) {
            SubmissionStatus::Incomplete => '<x-filament::badge color="gray" class="w-fit">'.SubmissionStatus::Incomplete->value.'</x-filament::badge>',
            SubmissionStatus::Queued => '<x-filament::badge color="primary" class="w-fit">'.SubmissionStatus::Queued->value.'</x-filament::badge>',
            SubmissionStatus::OnReview => '<x-filament::badge color="warning" class="w-fit">'.SubmissionStatus::OnReview->value.'</x-filament::badge>',
            SubmissionStatus::Published => '<x-filament::badge color="success" class="w-fit">'.SubmissionStatus::Published->value.'</x-filament::badge>',
            SubmissionStatus::Editing => '<x-filament::badge color="info" class="w-fit">'.SubmissionStatus::Editing->value.'</x-filament::badge>',
            SubmissionStatus::Declined => '<x-filament::badge color="danger" class="w-fit">'.SubmissionStatus::Declined->value.'</x-filament::badge>',
            SubmissionStatus::Withdrawn => '<x-filament::badge color="danger" class="w-fit">'.SubmissionStatus::Withdrawn->value.'</x-filament::badge>',
            default => null,
        };

        if ($this->record->hasPaymentProcess()) {
            $badgeHtml .= match ($this->record->payment?->state) {
                PaymentState::Unpaid => '<x-filament::badge color="danger" class="w-fit">Unpaid</x-filament::badge>',
                PaymentState::Processing => '<x-filament::badge color="primary" class="w-fit">Payment Processing</x-filament::badge>',
                PaymentState::Paid => '<x-filament::badge color="success" class="w-fit">Paid</x-filament::badge>',
                PaymentState::Waived => '<x-filament::badge color="success" class="w-fit">Payment Waived</x-filament::badge>',
                default => '<x-filament::badge color="danger" class="w-fit">Unpaid</x-filament::badge>',
            };
        }

        $badgeHtml .= '</div>';

        return new HtmlString(
            BladeCompiler::render($badgeHtml)
        );
    }

    public function getHeading(): string
    {
        return $this->record->getMeta('title');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                HorizontalTabs::make()
                    // ->persistTabInQueryString('tab')
                    ->contained(false)
                    ->tabs([
                        HorizontalTab::make(__('translation.submissions.viewSubMissionHorizontalTab'))
                            ->schema([
                                Tabs::make()
                                    ->verticalSpace('space-y-2')
                                    ->activeTab(function () {
                                        return match ($this->record->stage) {
                                            SubmissionStage::CallforAbstract => 1,
                                            SubmissionStage::PeerReview => 2,
                                            SubmissionStage::Editing, SubmissionStage::Proceeding => 3,
                                            default => null,
                                        };
                                    })
                                    // ->persistTabInQueryString('stage')
                                    ->sticky()
                                    ->tabs([
                                        Tab::make(__('translation.submissions.viewSubMissionTabCallForAbstract'))
                                            ->icon('heroicon-o-information-circle')
                                            ->schema(function () {
                                                if (! StageManager::callForAbstract()->isStageOpen() && ! $this->record->isPublished()) {
                                                    return [
                                                        ShoutEntry::make('call-for-abstract-closed')
                                                            ->type('warning')
                                                            ->color('warning')
                                                            ->content('Call for abstract stage is closed.'),
                                                    ];
                                                }

                                                return [
                                                    LivewireEntry::make('call-for-abstract')
                                                        ->livewire(CallforAbstract::class, [
                                                            'submission' => $this->record,
                                                        ]),
                                                ];
                                            }),
                                        Tab::make(__('translation.submissions.viewSubMissionTabPeerReview'))
                                            ->icon('iconpark-checklist-o')
                                            ->schema(function (): array {
                                                if (! StageManager::peerReview()->isStageOpen() && ! $this->record->isPublished()) {
                                                    return [
                                                        ShoutEntry::make('peer-review-closed')
                                                            ->type('warning')
                                                            ->color('warning')
                                                            ->content(__('translation.submissions.viewSubMissionTabPeerReviewContent')),
                                                    ];
                                                }

                                                return [
                                                    LivewireEntry::make('peer-review')
                                                        ->livewire(PeerReview::class, [
                                                            'submission' => $this->record,
                                                        ]),
                                                ];
                                            }),
                                        Tab::make(__('translation.submissions.viewSubMissionTabEditing'))
                                            ->icon('heroicon-o-pencil')
                                            ->schema(function () {
                                                if (! StageManager::editing()->isStageOpen() && ! $this->record->isPublished()) {
                                                    return [
                                                        ShoutEntry::make('editing-closed')
                                                            ->type('warning')
                                                            ->color('warning')
                                                            ->content(__('translation.submissions.viewSubMissionTabEditingContent')),
                                                    ];
                                                }

                                                return [
                                                    LivewireEntry::make('editing')
                                                        ->livewire(Editing::class, [
                                                            'submission' => $this->record,
                                                        ]),
                                                ];
                                            }),
                                    ])
                                    ->maxWidth('full'),
                            ]),
                        HorizontalTab::make(__('translation.submissions.viewSubMissionHorizontalTabPublication'))
                            ->extraAttributes([
                                'x-on:open-publication-tab.window' => new HtmlString('tab = \'-publication-tab\''),
                            ])
                            ->schema([
                                ShoutEntry::make('can-not-edit')
                                    ->type('warning')
                                    ->color('warning')
                                    ->visible(
                                        fn (): bool => $this->record->isPublished()
                                    )
                                    ->content(__('translation.submissions.viewSubMissionContentYouCantEditThis')),
                                Tabs::make()
                                    ->verticalSpace('space-y-2')
                                    // ->persistTabInQueryString('ptab') // ptab shorten of publication-tab
                                    ->tabs([
                                        Tab::make(__('translation.submissions.viewSubMissionDetail'))
                                            ->icon('heroicon-o-information-circle')
                                            ->schema([
                                                LivewireEntry::make('detail-form')
                                                    ->livewire(Detail::class, [
                                                        'submission' => $this->record,
                                                    ]),
                                            ]),
                                        Tab::make(__('translation.submissions.viewSubMissionDetailTitleContributors'))
                                            ->icon('heroicon-o-user-group')
                                            ->schema([
                                                LivewireEntry::make('contributors')
                                                    ->livewire(ContributorList::class, [
                                                        'submission' => $this->record,
                                                        'viewOnly' => ! auth()->user()->can('editing', $this->record),
                                                    ]),
                                            ]),
                                        Tab::make(__('translation.submissions.viewSubMissionDetailTitlePresenters'))
                                            ->icon('heroicon-o-user-group')
                                            ->schema([
                                                LivewireEntry::make('presenters')
                                                    ->livewire(PresenterList::class, [
                                                        'submission' => $this->record,
                                                        'viewOnly' => ! auth()->user()->can('editing', $this->record),
                                                    ]),
                                            ]),
                                        Tab::make(__('translation.submissions.viewSubMissionDetailTitleGalleys'))
                                            ->icon('heroicon-o-document-text')
                                            ->schema([
                                                LivewireEntry::make('galleys')
                                                    ->livewire(GalleyList::class, [
                                                        'submission' => $this->record,
                                                        'viewOnly' => ! auth()->user()->can('editing', $this->record),
                                                    ]),
                                            ]),
                                        Tab::make(__('translation.submissions.viewSubMissionDetailTitleProceeding'))
                                            ->icon('heroicon-o-book-open')
                                            ->schema([
                                                LivewireEntry::make('proceeding')
                                                    ->livewire(SubmissionProceeding::class, [
                                                        'submission' => $this->record,
                                                    ]),
                                            ]),
                                        Tab::make(__('translation.submissions.viewSubMissionDetailTitleReferences'))
                                            ->icon('iconpark-list')
                                            ->schema([
                                                LivewireEntry::make('references')
                                                    ->livewire(References::class, [
                                                        'submission' => $this->record,
                                                    ]),
                                            ]),
                                        Tab::make(__('translation.submissions.viewSubMissionDetailTitlePresenterFiles'))
                                            ->icon('heroicon-o-document-text')
                                            ->schema([
                                                LivewireEntry::make('presenter')
                                                    ->livewire(PresenterFiles::class, [
                                                        'submission' => $this->record,
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public function getTitle(): string
    {
        return $this->record->stage == SubmissionStage::Wizard ? 'Submission Wizard' : 'Submission';
    }
}
