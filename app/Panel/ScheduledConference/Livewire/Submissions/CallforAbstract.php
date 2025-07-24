<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions;

use App\Constants\SubmissionFileCategory;
use App\Forms\Components\TinyEditor;
use App\Mail\Templates\AcceptAbstractMail;
use App\Mail\Templates\DeclineAbstractMail;
use App\Models\DefaultMailTemplate;
use App\Models\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Notifications\AbstractAccepted;
use App\Notifications\AbstractDeclined;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
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
            ->authorize('actAsEditor', $this->submission)
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

    public function acceptAndSkipReview()
    {
        return Action::make('acceptAndSkipReview')
            ->label(__('general.skip_review'))
            ->icon('lineawesome-check-circle-solid')
            ->color('gray')
            ->outlined()
            ->requiresConfirmation()
            ->extraAttributes(['class' => 'w-full'])
            ->action(function (Action $action) {
                $this->submission->state()->acceptAndSkipReview();

                $action->successRedirectUrl(
                    SubmissionResource::getUrl('view', [
                        'record' => $this->submission->getKey(),
                    ])
                );

                $action->success();
            });
    }

    public function acceptAction()
    {
        return Action::make('accept')
            ->label(__('general.send_for_review'))
            ->authorize('actAsEditor', $this->submission)
            ->modalWidth('2xl')
            ->record($this->submission)
            ->successNotificationTitle(__('general.send_for_review'))
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
                CheckboxList::make('papers')
                    ->label('Select files below to send them to the review stage')
                    ->hidden(
                        ! $this->submission->getMedia(SubmissionFileCategory::ABSTRACT_FILES)->count()
                    )
                    ->options(function () {
                        return $this->submission
                            ->submissionFiles()
                            ->with(['media'])
                            ->where('category', SubmissionFileCategory::ABSTRACT_FILES)
                            ->get()
                            ->mapWithKeys(function (SubmissionFile $paper) {
                                return [
                                    $paper->getKey() => new HtmlString(
                                        Action::make($paper->media->file_name)
                                            ->label($paper->media->file_name)
                                            ->url(fn () => $paper->media->getTemporaryUrl(now()->addMinutes(5)))
                                            ->link()
                                            ->toHtml()
                                    ),
                                ];
                            });
                    })
                    ->descriptions(function () {
                        return $this->submission
                            ->submissionFiles()
                            ->where('category', SubmissionFileCategory::ABSTRACT_FILES)
                            ->get()
                            ->mapWithKeys(function (SubmissionFile $paper) {
                                return [$paper->getKey() => $paper->type->name];
                            });
                    }),
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

                        $submissionFiles = $this->submission
                            ->submissionFiles()
                            ->with(['media'])
                            ->whereIn('id', $data['papers'])
                            ->get();

                        foreach ($submissionFiles as $record) {
                            $clonedMedia = $record->media->copy(
                                $this->submission,
                                SubmissionFileCategory::PAPER_FILES,
                                'private-files'
                            );
                            $this->submission
                                ->submissionFiles()
                                ->create([
                                    'submission_file_type_id' => $record->type->getKey(),
                                    'category' => SubmissionFileCategory::PAPER_FILES,
                                    'media_id' => $clonedMedia->getKey(),
                                ]);
                        }

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

        return view('panel.scheduledConference.livewire.submissions.call-for-abstract', [
            'submissionDecision' => in_array($this->submission->status, [
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
