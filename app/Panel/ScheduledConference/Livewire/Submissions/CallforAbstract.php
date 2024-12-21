<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions;

use App\Forms\Components\TinyEditor;
use App\Mail\Templates\AcceptAbstractMail;
use App\Mail\Templates\DeclineAbstractMail;
use App\Models\DefaultMailTemplate;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Submission;
use App\Notifications\AbstractAccepted;
use App\Notifications\AbstractDeclined;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class CallforAbstract extends Component implements HasActions, HasForms
{
    use InteractsWithActions, InteractsWithForms;

    public Submission $submission;

    protected $listeners = [
        'refreshSubmission' => '$refresh',
    ];

    public function declineAction()
    {
        return Action::make('decline')
            ->outlined()
            ->color('danger')
            ->authorize('makeAbstractDecision', $this->submission)
            ->modalWidth('2xl')
            ->record($this->submission)
            ->modalHeading(__('general.confirmation'))
            ->modalSubmitActionLabel(__('general.decline'))
            ->extraAttributes(['class' => 'w-full'], true)
            ->mountUsing(function (Form $form): void {
                $mailTempalte = DefaultMailTemplate::where('mailable', DeclineAbstractMail::class)->first();
                $form->fill([
                    'subject' => $mailTempalte ? $mailTempalte->subject : '',
                    'message' => $mailTempalte ? $mailTempalte->html_template : '',
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
                            ->formatStateUsing(fn (Submission $record): string => $record->user->email),
                        TextInput::make('subject')
                            ->label(__('general.subject'))
                            ->required(),
                        TinyEditor::make('message')
                            ->label(__('general.message'))
                            ->minHeight(300)
                            ->profile('email'),
                        Checkbox::make('no-notification')
                            ->label(__('general.dont_send_notification_to_author'))
                            ->default(false),
                    ]),
            ])
            ->successNotificationTitle(__('general.submission_declined'))
            ->successRedirectUrl(fn (): string => SubmissionResource::getUrl('view', ['record' => $this->submission]))
            ->action(function (Action $action, array $data) {
                $this->submission->state()->decline();

                if (! $data['no-notification']) {
                    try {
                        $this->submission->user->notify(
                            new AbstractDeclined(
                                submission: $this->submission,
                                message: $data['message'],
                                subject: $data['subject'],
                                channels: ['mail']
                            )
                        );
                    } catch (\Exception $e) {
                        $action->failureNotificationTitle(__('general.email_notification_was_not_delivered'));
                        $action->failure();
                    }
                }

                $this->submission->user->notify(
                    new AbstractDeclined(
                        submission: $this->submission,
                        message: $data['message'],
                        subject: $data['subject'],
                        channels: ['database']
                    )
                );

                $action->success();
            })
            ->icon('lineawesome-times-circle-solid');
    }

    public function acceptAction()
    {
        $isPaymentRequired = app()->getCurrentScheduledConference()->isSubmissionRequirePayment();

        return Action::make('accept')
            ->modalHeading(__('general.confirmation'))
            ->modalSubmitActionLabel(fn () => $isPaymentRequired ? __('general.send_for_payment') : __('general.send_for_review'))
            ->authorize('makeAbstractDecision', $this->submission)
            ->modalWidth('2xl')
            ->record($this->submission)
            ->successNotificationTitle('Accepted')
            ->extraAttributes(['class' => 'w-full'])
            ->icon('lineawesome-check-circle-solid')
            ->mountUsing(function (Form $form): void {
                $mailTemplate = DefaultMailTemplate::where('mailable', AcceptAbstractMail::class)->first();
                $form->fill([
                    'subject' => $mailTemplate ? $mailTemplate->subject : '',
                    'message' => $mailTemplate ? $mailTemplate->html_template : '',
                ]);
            })
            ->form([
                Fieldset::make('Notification')
                    ->label(__('general.notification'))
                    ->columns(1)
                    ->schema([
                        /**
                         * TODO:
                         * - Need to create a function for it because it is used frequently.
                         *
                         * Something like:
                         *   UserNotificaiton::formSchema()
                         */
                        TextInput::make('email')
                            ->label(__('general.email'))
                            ->disabled()
                            ->formatStateUsing(fn (Submission $record): string => $record->user->email),
                        TextInput::make('subject')
                            ->label(__('general.subject'))
                            ->required(),
                        TinyEditor::make('message')
                            ->label(__('general.message'))
                            ->minHeight(300)
                            ->profile('email')
                            ->toolbarSticky(false),
                        Checkbox::make('no-notification')
                            ->label(__('general.dont_send_notification_to_author'))
                            ->default(false),
                    ]),
            ])
            ->action(
                function (Action $action, array $data) {
                    try {
                        $this->submission->state()->acceptAbstract();

                        if (! $data['no-notification']) {
                            try {
                                $this->submission->user
                                    ->notify(
                                        new AbstractAccepted(
                                            submission: $this->submission,
                                            message: $data['message'],
                                            subject: $data['subject'],
                                            channels: ['mail']
                                        )
                                    );
                            } catch (\Exception $e) {
                                $action->failureNotificationTitle(__('general.email_notification_was_not_delivered'));
                                $action->failure();
                            }
                        }

                        $this->submission->user
                            ->notify(
                                new AbstractAccepted(
                                    submission: $this->submission,
                                    message: $data['message'],
                                    subject: $data['subject'],
                                    channels: ['database']
                                )
                            );

                        $action->successRedirectUrl(
                            SubmissionResource::getUrl('view', [
                                'record' => $this->submission->getKey(),
                            ])
                        );

                        $action->success();
                    } catch (\Throwable $th) {
                        Log::error($th->getMessage());
                        $action->failureNotificationTitle(__('general.failed_to_accept_abstract'));
                        $action->failure();
                    }
                }
            );
    }

    public function render()
    {
        $user = auth()->user();

        return view('panel.scheduledConference.livewire.submissions.call-for-abstract', [
            'submissionDecision' => ($user->hasAnyRole([UserRole::ConferenceManager, UserRole::Admin]) || $this->submission->isParticipantEditor($user)) &&
            in_array($this->submission->status, [
                SubmissionStatus::OnPayment,
                SubmissionStatus::OnReview,
                SubmissionStatus::Editing,
                SubmissionStatus::Declined,
                SubmissionStatus::PaymentDeclined,
                SubmissionStatus::OnPresentation,
            ]),
        ]);
    }
}
