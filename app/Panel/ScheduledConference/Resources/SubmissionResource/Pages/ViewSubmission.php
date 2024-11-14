<?php

namespace App\Panel\ScheduledConference\Resources\SubmissionResource\Pages;

use App\Actions\Submissions\AcceptWithdrawalAction;
use App\Actions\Submissions\CancelWithdrawalAction;
use App\Actions\Submissions\RequestWithdrawalAction;
use App\Forms\Components\TinyEditor;
use App\Infolists\Components\LivewireEntry;
use App\Infolists\Components\VerticalTabs\Tab as Tab;
use App\Infolists\Components\VerticalTabs\Tabs as Tabs;
use App\Mail\Templates\PublishSubmissionMail;
use App\Models\DefaultMailTemplate;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\User;
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
use App\Panel\ScheduledConference\Livewire\Submissions\Payment;
use App\Panel\ScheduledConference\Livewire\Submissions\PeerReview;
use App\Panel\ScheduledConference\Livewire\Submissions\Presentation;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Awcodes\Shout\Components\ShoutEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
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
use Illuminate\View\Compilers\BladeCompiler;

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
            Action::make('view')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->outlined()
                ->url(route('livewirePageGroup.conference.pages.paper', ['submission' => $this->record->id]), true)
                ->label(function () {
                    if ($this->record->isPublished()) {
                        return __('general.view');
                    }

                    if (auth()->user()->can('editing', $this->record)) {
                        return __('general.preview');
                    }
                })
                ->visible(
                    fn (): bool => ($this->record->isPublished() || auth()->user()->can('editing', $this->record)) && $this->record->proceeding
                ),
            Action::make('assign_proceeding')
                ->label(__('general.publication'))
                ->authorize('publish', $this->record)
                ->modalHeading(__('general.assign_proceeding_for_publication'))
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
                ->label(__('general.publish_now'))
                ->visible(
                    fn (): bool => $this->record->proceeding ? true : false
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
                ->label(fn () => $this->record->proceeding?->isPublished() ? __('general.unpublish') : __('general.unschedule'))
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
                        ->placeholder(__('general.reason_for_disabling_user'))
                        ->label(__('general.reason')),
                ])
                ->requiresConfirmation()
                ->modalHeading(function () {
                    return $this->record->user->fullName.__('general.requested_withdraw_this_submission');
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
                ->hidden(
                    fn (): bool => $this->record->stage == SubmissionStage::Wizard
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
            SubmissionStatus::Incomplete => '<x-filament::badge color="gray" class="w-fit">'.__('general.incomplete').'</x-filament::badge>',
            SubmissionStatus::Queued => '<x-filament::badge color="primary" class="w-fit">'.__('general.queued').'</x-filament::badge>',
            SubmissionStatus::OnPayment => '<x-filament::badge color="warning" class="w-fit">'.__('general.on_payment').'</x-filament::badge>',
            SubmissionStatus::OnReview => '<x-filament::badge color="warning" class="w-fit">'.__('general.on_review').'</x-filament::badge>',
            SubmissionStatus::Published => $this->record->proceeding?->isPublished() ? '<x-filament::badge color="success" class="w-fit">'.__('general.published').'</x-filament::badge>' : '<x-filament::badge color="primary" class="w-fit">'.__('general.scheduled').'</x-filament::badge>',
            SubmissionStatus::Editing => '<x-filament::badge color="info" class="w-fit">'.__('general.editing').'</x-filament::badge>',
            SubmissionStatus::Declined => '<x-filament::badge color="danger" class="w-fit">'.__('general.declined').'</x-filament::badge>',
            SubmissionStatus::PaymentDeclined => '<x-filament::badge color="danger" class="w-fit">'.__('general.payment_declined').'</x-filament::badge>',
            SubmissionStatus::Withdrawn => '<x-filament::badge color="danger" class="w-fit">'.__('general.withdrawn').'</x-filament::badge>',
            default => null,
        };

        $badgeHtml .= '</div>';

        return new HtmlString(
            BladeCompiler::render($badgeHtml)
        );
    }

    public function getHeading(): string
    {
        return $this->record->getMeta('title') ?? '';
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $isPaymentRequired = app()->getCurrentScheduledConference()->isSubmissionRequirePayment();

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
                                    ->activeTab(function () use ($isPaymentRequired) {
                                        return match ($this->record->stage) {
                                            SubmissionStage::CallforAbstract => 1,
                                            SubmissionStage::Payment => 2,
                                            SubmissionStage::PeerReview => $isPaymentRequired ? 3 : 2,
                                            SubmissionStage::Presentation => $isPaymentRequired ? 4 : 3,
                                            SubmissionStage::Editing, SubmissionStage::Proceeding => $isPaymentRequired ? 5 : 4,
                                            default => null,
                                        };
                                    })
                                    ->sticky()
                                    ->tabs([
                                        Tab::make('Call for Abstract')
                                            ->label(__('general.call_for_abstract'))
                                            ->icon('heroicon-o-information-circle')
                                            ->schema([
                                                LivewireEntry::make('call-for-abstract')
                                                    ->livewire(CallforAbstract::class, [
                                                        'submission' => $this->record,
                                                    ]),
                                            ]),
                                        Tab::make('Payment')
                                            ->label(__('general.payment'))
                                            ->icon('heroicon-o-credit-card')
                                            ->schema([
                                                LivewireEntry::make('payment')
                                                    ->livewire(Payment::class, [
                                                        'submission' => $this->record,
                                                    ]),
                                            ])
                                            ->hidden(fn () => ! $isPaymentRequired),
                                        Tab::make('Peer Review')
                                            ->label(__('general.peer_review'))
                                            ->icon('iconpark-checklist-o')
                                            ->schema([
                                                LivewireEntry::make('peer-review')
                                                    ->livewire(PeerReview::class, [
                                                        'submission' => $this->record,
                                                    ]),
                                            ]),
                                        Tab::make('Presentation')
                                            ->label(__('general.presentation'))
                                            ->icon('heroicon-o-presentation-chart-bar')
                                            ->schema([
                                                LivewireEntry::make('presentation')
                                                    ->livewire(Presentation::class, [
                                                        'submission' => $this->record,
                                                    ]),
                                            ]),
                                        Tab::make('Editing')
                                            ->label(__('general.editing'))
                                            ->icon('heroicon-o-pencil')
                                            ->schema([
                                                LivewireEntry::make('editing')
                                                    ->livewire(Editing::class, [
                                                        'submission' => $this->record,
                                                    ]),
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
                                        fn (): bool => $this->record->isPublished()
                                    )
                                    ->content(__('general.cant_edit_submission_because_already_published')),
                                Tabs::make()
                                    // ->persistTabInQueryString('ptab') // ptab shorten of publication-tab
                                    ->tabs([
                                        Tab::make('Detail')
                                            ->label(__('general.details'))
                                            ->icon('heroicon-o-information-circle')
                                            ->schema([
                                                LivewireEntry::make('detail-form')
                                                    ->livewire(Detail::class, [
                                                        'submission' => $this->record,
                                                    ]),
                                            ]),
                                        Tab::make('Contributors')
                                            ->label(__('general.contributors'))
                                            ->icon('heroicon-o-user-group')
                                            ->schema([
                                                LivewireEntry::make('contributors')
                                                    ->livewire(ContributorList::class, [
                                                        'submission' => $this->record,
                                                        'viewOnly' => ! auth()->user()->can('editing', $this->record),
                                                    ]),
                                            ]),
                                        Tab::make('Galleys')
                                            ->label(__('general.galleys'))
                                            ->icon('heroicon-o-document-text')
                                            ->schema([
                                                LivewireEntry::make('galleys')
                                                    ->livewire(GalleyList::class, [
                                                        'submission' => $this->record,
                                                        'viewOnly' => ! auth()->user()->can('editing', $this->record),
                                                    ]),
                                            ]),
                                        Tab::make('Proceeding')
                                            ->label(__('general.proceeding'))
                                            ->icon('heroicon-o-book-open')
                                            ->schema([
                                                LivewireEntry::make('proceeding')
                                                    ->livewire(SubmissionProceeding::class, [
                                                        'submission' => $this->record,
                                                    ]),
                                            ]),
                                        Tab::make('Permissions and Disclosure')
                                            ->label(__('general.permissions_and_disclosure'))
                                            ->icon('heroicon-o-shield-exclamation')
                                            ->schema([
                                                LivewireEntry::make('permissions-and-disclosure')
                                                    ->livewire(PermissionsAndDisclosure::class, [
                                                        'submission' => $this->record,
                                                    ]),
                                            ]),
                                        Tab::make('References')
                                            ->label(__('general.references'))
                                            ->icon('iconpark-list')
                                            ->schema([
                                                LivewireEntry::make('references')
                                                    ->livewire(References::class, [
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
        return $this->record->stage == SubmissionStage::Wizard ? __('general.submission_wizard') : __('general.submission');
    }
}
