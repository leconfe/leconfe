<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions;

use App\Models\Review;
use Livewire\Component;
use Filament\Forms\Form;
use App\Models\PaymentFee;
use App\Models\Submission;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use App\Managers\PaymentManager;
use Filament\Support\Colors\Color;
use App\Models\DefaultMailTemplate;
use Filament\Forms\Components\Grid;
use App\Forms\Components\TinyEditor;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Mail;
use App\Models\Enums\SubmissionStage;
use Filament\Forms\Components\Select;
use App\Models\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Contracts\HasForms;
use App\Mail\Templates\AcceptPaperMail;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use App\Mail\Templates\DeclinePaperMail;
use Filament\Forms\Components\TextInput;
use Filament\Actions\Contracts\HasActions;
use App\Mail\Templates\RevisionRequestMail;
use Filament\Forms\Concerns\InteractsWithForms;
use App\Actions\Submissions\SubmissionUpdateAction;
use App\Notifications\PaymentRequired;
use Filament\Actions\Concerns\InteractsWithActions;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Squire\Models\Currency;

class PeerReview extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public Submission $submission;

    protected $listeners = [
        'refreshSubmission' => '$refresh',
    ];

    public function mount(Submission $submission) {}

    public function declineSubmissionAction()
    {
        return Action::make('declineSubmissionAction')
            ->icon('lineawesome-times-solid')
            ->authorize('declinePaper', $this->submission)
            ->label(__('general.decline_submission'))
            ->color('danger')
            ->outlined()
            ->mountUsing(function (Form $form) {
                $mailTemplate = DefaultMailTemplate::where('mailable', DeclinePaperMail::class)->first();
                $form->fill([
                    'email' => $this->submission->user->email,
                    'subject' => $mailTemplate ? $mailTemplate->subject : '',
                    'message' => $mailTemplate ? $mailTemplate->html_template : '',
                ]);
            })
            ->form([
                Fieldset::make('Notification')
                    ->columns(1)
                    ->schema([
                        TextInput::make('email')
                            ->label(__('general.email'))
                            ->readOnly()
                            ->dehydrated(),
                        TextInput::make('subject')
                            ->label(__('general.subject'))
                            ->required(),
                        TinyEditor::make('message')
                            ->label(__('general.message'))
                            ->minHeight(300)
                            ->profile('email')
                            ->columnSpanFull(),
                        Checkbox::make('do-not-notify-author')
                            ->label(__('general.dont_send_notification_to_author'))
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (Action $action, array $data) {
                $this->submission->state()->decline();

                if (! $data['do-not-notify-author']) {
                    try {
                        Mail::to($this->submission->user->email)
                            ->send(
                                (new DeclinePaperMail($this->submission))
                                    ->subjectUsing($data['subject'])
                                    ->contentUsing($data['message'])
                            );
                    } catch (\Exception $e) {
                        $action->failureNotificationTitle(__('general.email_notification_was_not_delivered'));
                        $action->failure();
                    }
                }

                $action->successRedirectUrl(
                    SubmissionResource::getUrl('view', [
                        'record' => $this->submission->getKey(),
                    ])
                );

                $action->success();
            });
    }

    public function acceptSubmissionAction()
    {
        return Action::make('acceptSubmissionAction')
            ->authorize('acceptPaper', $this->submission)
            ->icon('lineawesome-check-circle-solid')
            ->color('primary')
            ->label(__('general.accept_submission'))
            ->modalSubmitActionLabel(__('general.accept'))
            ->mountUsing(function (Form $form) {
                $mailTemplate = DefaultMailTemplate::where('mailable', AcceptPaperMail::class)->first();
                $form->fill([
                    'email' => $this->submission->user->email,
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
                            ->readOnly()
                            ->dehydrated(),
                        TextInput::make('subject')
                            ->label(__('general.subject'))
                            ->required(),
                        TinyEditor::make('message')
                            ->label(__('general.message'))
                            ->minHeight(300)
                            ->profile('email')
                            ->columnSpanFull(),
                        Checkbox::make('do-not-notify-author')
                            ->label(__('general.dont_send_notification_to_author'))
                            ->columnSpanFull(),
                    ]),
                Grid::make()
                    ->visible(fn() => !$this->submission->paymentCompleted() && app()->getCurrentScheduledConference()->getMeta('submission_payment'))
                    ->schema([
                        Radio::make('payment_fee')
                            ->required()
                            ->options(
                                fn() => PaymentFee::type(PaymentManager::TYPE_SUBMISSION_FEE)->active()->get()
                                    ->mapWithKeys(function ($record) {
                                        return [
                                            $record->getKey() => $record->name . ' (' . money($record->amount, $record->currency, true)->formatWithoutZeroes() . ')'
                                        ];
                                    })
                                    ->put('custom', 'Custom')
                                    ->put('waive', 'Waive')
                            )
                            ->reactive(),
                        Grid::make(1)
                            ->visible(fn(Get $get) => $get('payment_fee') == 'custom')
                            ->schema([
                                Grid::make()
                                    ->schema([
                                        Select::make('currency')
                                            ->label(__('general.currency'))
                                            ->formatStateUsing(fn($state) => ($state !== null) ? ($state !== 'free' ? $state : null) : null)
                                            ->options(fn() => Currency::query()->orderBy('code_numeric', 'asc')->get()
                                                ->mapWithKeys(function (?Currency $value, int $key) {
                                                    $currencyCode = Str::upper($value->id);
                                                    $currencyName = $value->name;

                                                    return [$value->id => "($currencyCode) $currencyName"];
                                                }))
                                            ->searchable()
                                            ->required(),
                                        TextInput::make('amount')
                                            ->label('Amount')
                                            ->numeric()
                                            ->required()
                                            ->minValue(1),
                                    ]),
                                Textarea::make('payment_description'),
                            ]),
                    ]),
            ])
            ->action(function (Action $action, array $data) {
                $this->submission->state()->sendToPresentation();

                if (! $data['do-not-notify-author']) {
                    try {
                        Mail::to($this->submission->user->email)
                            ->send(
                                (new AcceptPaperMail($this->submission))
                                    ->subjectUsing($data['subject'])
                                    ->contentUsing($data['message'])
                            );
                    } catch (\Exception $e) {
                        $action->failureNotificationTitle(__('general.email_notification_was_not_delivered'));
                        $action->failure();
                    }
                }

                if (!$this->submission->paymentCompleted() && app()->getCurrentScheduledConference()->getMeta('submission_payment')) {
                    $paymentFeeId = data_get($data, 'payment_fee');
                    switch ($paymentFeeId) {
                        case 'custom':
                            break;
                        case 'waive':
                            break;
                        default:
                            $paymentFee = PaymentFee::find($paymentFeeId);

                            $data['currency'] = $paymentFee->currency;
                            $data['amount'] = $paymentFee->amount;
                            $data['payment_description'] = $paymentFee->getMeta('description');
                            break;
                    }


                    $paymentManager = PaymentManager::get();
                    $paymentQueue = $paymentManager->queue(
                        $this->submission->getMeta('title'),
                        PaymentManager::TYPE_SUBMISSION_FEE,
                        $this->submission->user,
                        $this->submission,
                        $data['amount'],
                        $data['currency'],
                        $data['payment_description'],
                    );

                    $this->submission->user->notify(new PaymentRequired($paymentQueue));
                }

                $action->successRedirectUrl(
                    SubmissionResource::getUrl('view', [
                        'record' => $this->submission->getKey(),
                    ])
                );

                $action->success();
            });
    }

    public function requestRevisionAction()
    {
        return Action::make('requestRevisionAction')
            ->authorize('requestRevision', $this->submission)
            ->hidden(fn(): bool => $this->submission->revision_required)
            ->icon('lineawesome-list-alt-solid')
            ->outlined()
            ->color(Color::Orange)
            ->label(__('general.request_revision'))
            ->mountUsing(function (Form $form): void {
                $mailTemplate = DefaultMailTemplate::where('mailable', RevisionRequestMail::class)->first();
                $form->fill([
                    'email' => $this->submission->user->email,
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
                            ->readOnly()
                            ->dehydrated(),
                        TextInput::make('subject')
                            ->label(__('general.subject'))
                            ->required(),
                        TinyEditor::make('message')
                            ->label(__('general.message'))
                            ->minHeight(300)
                            ->profile('email')
                            ->columnSpanFull(),
                        Checkbox::make('do-not-notify-author')
                            ->label(__('general.dont_send_notification_to_author'))
                            ->columnSpanFull(),
                    ]),
            ])
            ->successNotificationTitle(__('general.revision_requested'))
            ->action(function (Action $action, array $data) {
                SubmissionUpdateAction::run([
                    'revision_required' => true,
                    'status' => SubmissionStatus::OnReview,
                    'stage' => SubmissionStage::PeerReview,
                ], $this->submission);

                if (! $data['do-not-notify-author']) {
                    try {
                        Mail::to($this->submission->user->email)
                            ->send(
                                (new RevisionRequestMail($this->submission))
                                    ->subjectUsing($data['subject'])
                                    ->contentUsing($data['message'])
                            );
                    } catch (\Exception $e) {
                        $action->failureNotificationTitle(__('general.email_notification_was_not_delivered'));
                        $action->failure();
                    }
                }

                $action->successRedirectUrl(
                    SubmissionResource::getUrl('view', [
                        'record' => $this->submission->getKey(),
                    ])
                );

                $action->success();
            });
    }

    public function render()
    {
        if ($this->submission->status->isBefore(SubmissionStatus::OnReview)) {
            return view('panel.scheduledConference.livewire.submissions.message', ['message' => 'Stage not initiated']);
        }

        if ($this->submission->skipped_review) {
            return view('panel.scheduledConference.livewire.submissions.message', ['message' => __('general.review_skipped')]);
        }

        return view('panel.scheduledConference.livewire.submissions.peer-review', [
            'submissionDecision' => in_array($this->submission->status, [
                SubmissionStatus::Editing,
                SubmissionStatus::Declined,
                SubmissionStatus::OnPresentation,
            ]),
        ]);
    }
}
