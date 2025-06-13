<?php

namespace App\Panel\ScheduledConference\Resources\SubmissionResource\Pages;

use App\Actions\Submissions\AcceptWithdrawalAction;
use App\Actions\Submissions\CancelWithdrawalAction;
use App\Actions\Submissions\RequestWithdrawalAction;
use App\Forms\Components\TinyEditor;
use App\Infolists\Components\VerticalTabs\Tab as Tab;
use App\Infolists\Components\VerticalTabs\Tabs as Tabs;
use App\Mail\Templates\PublishSubmissionMail;
use App\Managers\PaymentManager;
use App\Models\DefaultMailTemplate;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\PaymentFee;
use App\Models\User;
use App\Notifications\PaymentRequired;
use App\Notifications\SubmissionWithdrawn;
use App\Notifications\SubmissionWithdrawRequested;
use App\Panel\ScheduledConference\Livewire\Submissions\CallforAbstract;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ActivityLogList;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ContributorList;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\GalleyList;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\PermissionsAndDisclosure;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\SubmissionProceeding;
use App\Panel\ScheduledConference\Livewire\Submissions\Editing;
use App\Panel\ScheduledConference\Livewire\Submissions\Forms\Detail;
use App\Panel\ScheduledConference\Livewire\Submissions\Forms\References;
use App\Panel\ScheduledConference\Livewire\Submissions\PeerReview;
use App\Panel\ScheduledConference\Livewire\Submissions\Presentation;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Awcodes\Shout\Components\ShoutEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Tabs as HorizontalTabs;
use Filament\Infolists\Components\Tabs\Tab as HorizontalTab;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;
use Squire\Models\Currency;

class ViewSubmission extends Page implements HasForms, HasInfolists
{
    use InteractsWithForms, InteractsWithInfolists, InteractsWithRecord;

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
                ->visible(app()->getCurrentScheduledConference()->getMeta('submission_payment'))
                ->modalWidth(MaxWidth::Large)
                ->modalCancelActionLabel(__('general.close'))
                ->visible(fn() => $this->record->payment)
                ->authorize(fn() => $this->record->isParticipantAuthor(auth()->user()))
                ->url(fn() => $this->record->payment->getPaymentUrl())
                ->hidden(fn() => $this->record->payment?->isPaid()),
            Action::make('charge_payment')
                ->label('Charge Payment')
                ->visible(fn() => ! $this->record->payment && app()->getCurrentScheduledConference()->getMeta('submission_payment'))
                ->authorize(fn() => auth()->user()->can('actAsEditor', $this->record))
                ->icon('heroicon-o-banknotes')
                ->modalWidth(MaxWidth::Large)
                ->form(function (Form $form) {
                    return $form
                        ->model($this->record->payment)
                        ->schema([
                            Radio::make('payment_fee_id')
                                ->label('Payment Fee')
                                ->required()
                                ->options(
                                    fn() => PaymentFee::type(PaymentManager::TYPE_SUBMISSION_FEE)
                                        ->active()
                                        ->get()
                                        ->mapWithKeys(function ($record) {
                                            return [
                                                $record->getKey() => $record->name . ' (' . money($record->amount, $record->currency, true)->formatWithoutZeroes() . ')',
                                            ];
                                        })
                                )
                                ->afterStateUpdated(function (Set $set, $state) {
                                    if (! $state) {
                                        return;
                                    }

                                    $paymentFee = PaymentFee::find($state);
                                    $set('currency', $paymentFee->currency);
                                    $set('amount', $paymentFee->amount);
                                    $set('description', $paymentFee->getMeta('description'));
                                })
                                ->reactive(),
                            Grid::make(1)
                                ->visible(fn(Get $get) => $get('payment_fee_id'))
                                ->schema([
                                    Grid::make()
                                        ->schema([
                                            Select::make('currency')
                                                ->label(__('general.currency'))
                                                ->formatStateUsing(fn($state) => ($state !== null) ? ($state !== 'free' ? $state : null) : null)
                                                ->options(
                                                    fn() => Currency::query()->orderBy('code_numeric', 'asc')
                                                        ->get()
                                                        ->mapWithKeys(function (?Currency $value, int $key) {
                                                            $currencyCode = Str::upper($value->id);
                                                            $currencyName = $value->name;

                                                            return [$value->id => "($currencyCode) $currencyName"];
                                                        })
                                                )
                                                ->searchable()
                                                ->required(),
                                            TextInput::make('amount')
                                                ->label('Amount')
                                                ->numeric()
                                                ->required()
                                                ->minValue(0),
                                        ]),
                                    Textarea::make('description'),
                                ]),
                        ]);
                })
                ->successNotificationTitle('Payment fee sent to user')
                ->action(function (Action $action, array $data) {
                    $paymentManager = PaymentManager::get();

                    $paymentFeeId = data_get($data, 'payment_fee_id');

                    $paymentFee = PaymentFee::find($paymentFeeId);

                    $paymentQueue = $paymentManager->queue(
                        $this->record,
                        $paymentFee,
                        $this->record->user,
                        PaymentManager::TYPE_SUBMISSION_FEE,
                        $this->record->getMeta('title'),
                        SubmissionResource::getUrl('view', ['record' => $this->record]),
                        $data['description'],
                        $data['amount'],
                        $data['currency'],
                    );

                    $this->record->user->notify(new PaymentRequired($paymentQueue));

                    $action->success();
                }),
            Action::make('pay_submission_fee')
                ->label('Pay Submission Fee')
                ->authorize(fn() => $this->record->isParticipantAuthor(auth()->user()))
                ->icon('heroicon-o-credit-card')
                ->visible(fn() => $this->record->paymentQueue)
                ->url(fn() => $this->record->paymentQueue->getPaymentUrl()),
            // Action::make('view')
            //     ->icon('heroicon-o-eye')
            //     ->color('primary')
            //     ->outlined()
            //     ->url(route('livewirePageGroup.conference.pages.paper', ['submission' => $this->record->id]), true)
            //     ->label(function () {
            //         if ($this->record->isPublished()) {
            //             return __('general.view');
            //         }

            //         if (auth()->user()->can('editing', $this->record)) {
            //             return __('general.preview');
            //         }
            //     })
            //     ->visible(
            //         fn(): bool => ($this->record->isPublished() || auth()->user()->can('editing', $this->record)) && $this->record->proceeding
            //     ),
            Action::make('assign_proceeding')
                ->label(__('general.publication'))
                ->authorize('publish', $this->record)
                ->modalHeading(__('general.assign_proceeding_for_publication'))
                ->visible(fn() => ! $this->record->proceeding && $this->record->stage == SubmissionStage::Editing)
                ->modalWidth(MaxWidth::ExtraLarge)
                ->form(SubmissionProceeding::getFormAssignProceeding($this->record))
                ->action(function (array $data) {
                    SubmissionProceeding::assignProceeding($this->record, $data);

                    $this->replaceMountedAction('publish');
                    $this->dispatch('refreshSubmissionProceeding');
                }),
            Action::make('publish')
                ->color('primary')
                ->label(__('general.publish_now'))
                ->visible(
                    fn(): bool => $this->record->proceeding ? true : false
                )
                ->authorize('publish', $this->record)
                ->successNotificationTitle(__('general.assign_proceeding_for_publication'))
                ->mountUsing(function (Form $form) {
                    $mailTemplate = DefaultMailTemplate::where('mailable', PublishSubmissionMail::class)->first();
                    $form->fill([
                        'email' => $this->record->user->email,
                        'subject' => $mailTemplate ? $mailTemplate->subject : '',
                        'message' => $mailTemplate ? $mailTemplate->html_template : '',
                    ]);
                })
                ->form([
                    Fieldset::make('Notification')
                        ->label(__('general.notification'))
                        ->columns(1)
                        ->schema([
                            TextInput::make('email')
                                ->label(__('general.email'))
                                ->disabled()
                                ->dehydrated(),
                            TextInput::make('subject')
                                ->label(__('general.subject'))
                                ->required(),
                            TinyEditor::make('message')
                                ->label(__('general.message'))
                                ->profile('email')
                                ->minHeight(300),
                            Checkbox::make('do-not-notify-author')
                                ->label(__('general.dont_send_notification_to_author')),
                        ]),
                ])
                ->action(function (Action $action, array $data) {
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
                            $action->failureNotificationTitle(__('general.failed_send_notification_to_author'));
                            $action->failure();
                        }
                    }
                    $action->successRedirectUrl(
                        SubmissionResource::getUrl('view', [
                            'record' => $this->record->getKey(),
                        ])
                    );
                    $action->success();
                }),
            Action::make('unpublish')
                ->label(fn() => $this->record->proceeding?->isPublished() ? __('general.unpublish') : __('general.unschedule'))
                ->icon('lineawesome-calendar-times-solid')
                ->color('danger')
                ->authorize('unpublish', $this->record)
                ->requiresConfirmation()
                ->successNotificationTitle(__('general.submission_unpublished'))
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
                ->label(__('general.request_for_withdrawal'))
                ->icon('lineawesome-times-circle-solid')
                ->form([
                    Textarea::make('reason')
                        ->required()
                        ->placeholder(__('general.reason_for_withdrawal'))
                        ->label(__('general.reason')),
                ])
                ->requiresConfirmation()
                ->successNotificationTitle(__('general.withdraw_requested_please_wait_for_editor_approve'))
                ->action(function (Action $action, array $data) {
                    RequestWithdrawalAction::run(
                        $this->record,
                        $data['reason']
                    );

                    try {
                        // Currently using admin, next is admin removed only managers
                        User::whereHas(
                            'roles',
                            fn($query) => $query->whereIn('name', [UserRole::Admin->value, UserRole::ConferenceManager->value])
                        )
                            ->get()
                            ->each(
                                fn($manager) => $manager->notify(new SubmissionWithdrawRequested($this->record))
                            );

                        $this
                            ->record
                            ->getEditors()
                            ->each(function (User $editor) {
                                $editor->notify(new SubmissionWithdrawRequested($this->record));
                            });
                    } catch (\Exception $e) {
                        $action->failureNotificationTitle(__('general.failed_send_notification'));
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
                        $attributeValue = '$nextTick(() => { $wire.mountAction(\'' . $action->getName() . '\') })';

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
                        ->placeholder(__('general.reason_for_disabling_user'))
                        ->label(__('general.reason')),
                ])
                ->requiresConfirmation()
                ->modalHeading(function () {
                    return $this->record->user->fullName . __('general.requested_withdraw_this_submission');
                })
                ->modalDescription(__('general.either_reject_request_or_accept'))
                ->modalCancelActionLabel(__('general.ignore'))
                ->modalSubmitActionLabel(__('general.withdrawn'))
                ->successNotificationTitle(__('general.withdrawn'))
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
                            $action->successNotificationTitle(__('general.withdrawal_request_rejected'));
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
                        $action->failureNotificationTitle(__('general.failed_send_notification'));
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
                ->label(__('general.activity_log'))
                ->authorize(fn() => auth()->user()->can('actAsEditor', $this->record))
                ->hidden(
                    fn(): bool => $this->record->stage == SubmissionStage::Wizard
                )
                ->outlined()
                ->icon('lineawesome-history-solid')
                ->modalHeading(__('general.activity_log'))
                ->modalDescription(__('general.activity_log_submissions'))
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('general.close'))
                ->infolist(function () {
                    return [
                        Livewire::make(ActivityLogList::class, ['submission' => $this->record])
                            ->key('activites-table'),
                    ];
                }),
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        $badgeHtml = '<div class="flex items-center gap-x-2">';

        $badgeHtml .= match ($this->record->status) {
            SubmissionStatus::Incomplete => '<x-filament::badge color="gray" class="w-fit">' . __('general.incomplete') . '</x-filament::badge>',
            SubmissionStatus::Queued => '<x-filament::badge color="primary" class="w-fit">' . __('general.queued') . '</x-filament::badge>',
            SubmissionStatus::OnReview => '<x-filament::badge color="warning" class="w-fit">' . __('general.on_review') . '</x-filament::badge>',
            SubmissionStatus::OnPresentation => '<x-filament::badge color="warning" class="w-fit">' . __('general.on_presentation') . '</x-filament::badge>',
            SubmissionStatus::Published => $this->record->proceeding?->isPublished() ? '<x-filament::badge color="success" class="w-fit">' . __('general.published') . '</x-filament::badge>' : '<x-filament::badge color="primary" class="w-fit">' . __('general.scheduled') . '</x-filament::badge>',
            SubmissionStatus::Editing => '<x-filament::badge color="info" class="w-fit">' . __('general.editing') . '</x-filament::badge>',
            SubmissionStatus::Declined => '<x-filament::badge color="danger" class="w-fit">' . __('general.declined') . '</x-filament::badge>',
            SubmissionStatus::PaymentDeclined => '<x-filament::badge color="danger" class="w-fit">' . __('general.payment_declined') . '</x-filament::badge>',
            SubmissionStatus::Withdrawn => '<x-filament::badge color="danger" class="w-fit">' . __('general.withdrawn') . '</x-filament::badge>',
            default => null,
        };

        $badgeHtml .= '</div>';

        return new HtmlString(
            BladeCompiler::render($badgeHtml)
        );
    }

    public function getHeading(): string|Htmlable
    {
        return new HtmlString('<span class="text-xl ">' . $this->record->getMeta('title') . '</span>');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                HorizontalTabs::make()
                    // ->persistTabInQueryString('tab')
                    ->contained(false)
                    ->tabs([
                        HorizontalTab::make('Workflow')
                            ->label(__('general.workflow'))
                            ->schema([
                                Tabs::make()
                                    ->activeTab(function () {
                                        return match ($this->record->stage) {
                                            SubmissionStage::CallforAbstract => 1,
                                            SubmissionStage::PeerReview => 2,
                                            SubmissionStage::Presentation => 3,
                                            SubmissionStage::Editing, SubmissionStage::Proceeding => 4,
                                            default => null,
                                        };
                                    })
                                    ->sticky()
                                    ->tabs([
                                        Tab::make('Submission')
                                            ->label(__('general.submission'))
                                            ->icon('heroicon-o-information-circle')
                                            ->schema([
                                                Livewire::make(CallforAbstract::class, [
                                                    'submission' => $this->record,
                                                ])
                                                    ->key('call-for-abstract'),
                                            ]),
                                        Tab::make('Peer Review')
                                            ->label(__('general.peer_review'))
                                            ->icon('iconpark-checklist-o')
                                            ->schema([
                                                Livewire::make(PeerReview::class, [
                                                    'submission' => $this->record,
                                                ])
                                                    ->key('peer-review'),
                                            ]),
                                        Tab::make('Presentation')
                                            ->label(__('general.presentation'))
                                            ->icon('heroicon-o-presentation-chart-bar')
                                            ->schema([
                                                Livewire::make(Presentation::class, [
                                                    'submission' => $this->record,
                                                ])
                                                    ->key('presentation'),
                                            ]),
                                        Tab::make('Editing')
                                            ->label(__('general.editing'))
                                            ->icon('heroicon-o-pencil')
                                            ->schema([
                                                Livewire::make(Editing::class, [
                                                    'submission' => $this->record,
                                                ])
                                                    ->key('editing'),
                                            ]),
                                    ])
                                    ->maxWidth('full'),
                            ]),
                        HorizontalTab::make('Publication')
                            ->label(__('general.publication'))
                            ->extraAttributes([
                                'x-on:open-publication-tab.window' => new HtmlString('tab = \'-publication-tab\''),
                            ])
                            ->schema([
                                ShoutEntry::make('can-not-edit')
                                    ->type('warning')
                                    ->color('warning')
                                    ->visible(
                                        fn(): bool => $this->record->isPublished()
                                    )
                                    ->content(__('general.cant_edit_submission_because_already_published')),
                                Tabs::make()
                                    // ->persistTabInQueryString('ptab') // ptab shorten of publication-tab
                                    ->tabs([
                                        Tab::make('Detail')
                                            ->label(__('general.details'))
                                            ->icon('heroicon-o-information-circle')
                                            ->schema([
                                                Livewire::make(Detail::class, ['submission' => $this->record])
                                                    ->key('detail-form'),
                                            ]),
                                        Tab::make('Contributors')
                                            ->label(__('general.contributors'))
                                            ->icon('heroicon-o-user-group')
                                            ->schema([
                                                Livewire::make(ContributorList::class, [
                                                    'submission' => $this->record,
                                                    'viewOnly' => ! auth()->user()->can('editing', $this->record),
                                                ])
                                                    ->key('contributors'),
                                            ]),
                                        Tab::make('Galleys')
                                            ->label(__('general.galleys'))
                                            ->icon('heroicon-o-document-text')
                                            ->schema([
                                                Livewire::make(GalleyList::class, [
                                                    'submission' => $this->record,
                                                    'viewOnly' => ! auth()->user()->can('editing', $this->record),
                                                ])
                                                    ->key('galleys'),
                                            ]),
                                        Tab::make('Proceeding')
                                            ->label(__('general.proceeding'))
                                            ->icon('heroicon-o-book-open')
                                            ->schema([
                                                Livewire::make(SubmissionProceeding::class, ['submission' => $this->record])
                                                    ->key('proceeding'),
                                            ]),
                                        Tab::make('Permissions and Disclosure')
                                            ->label(__('general.permissions_and_disclosure'))
                                            ->icon('heroicon-o-shield-exclamation')
                                            ->schema([
                                                Livewire::make(PermissionsAndDisclosure::class, ['submission' => $this->record])
                                                    ->key('permissions-and-disclosure'),
                                            ]),
                                        Tab::make('References')
                                            ->label(__('general.references'))
                                            ->icon('iconpark-list')
                                            ->schema([
                                                Livewire::make(References::class, ['submission' => $this->record])
                                                    ->key('references'),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public function getTitle(): string|Htmlable
    {
        if ($this->record->stage == SubmissionStage::Wizard) {
            return __('general.submission_wizard');
        }

        return $this->record->getMeta('title') ?? __('general.submission');
    }

    public function getBreadcrumb(): ?string
    {
        return 'View';
    }
}
