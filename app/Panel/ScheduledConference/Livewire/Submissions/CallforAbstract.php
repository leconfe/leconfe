<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions;

use App\Actions\Submissions\StartSubmissionReviewRoundAction;
use App\Constants\SubmissionFileCategory;
use App\Forms\Components\TinyEditor;
use App\Mail\Templates\AcceptPaperMail;
use App\Mail\Templates\DeclineAbstractMail;
use App\Mail\Templates\SendForReviewMail;
use App\Managers\PaymentManager;
use App\Models\DefaultMailTemplate;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\PaymentFee;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Notifications\AbstractDeclined;
use App\Notifications\SubmissionSentForReview;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
            ])
            ->extraAttributes(['class' => 'w-full'])
            ->action(function (Action $action, array $data) {
                $this->submission->state()->acceptAndSkipReview();

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

                $action->successRedirectUrl(
                    SubmissionResource::getUrl('view', [
                        'record' => $this->submission->getKey(),
                    ])
                );

                $action->success();
            });
    }

    public function sendForReviewAction()
    {
        return Action::make('sendForReview')
            ->label(__('general.send_for_review'))
            ->authorize('actAsEditor', $this->submission)
            ->modalWidth('2xl')
            ->record($this->submission)
            ->successNotificationTitle(__('general.send_for_review'))
            ->extraAttributes(['class' => 'w-full'])
            ->icon('lineawesome-check-circle-solid')
            ->mountUsing(function (Form $form): void {
                $mailTemplate = DefaultMailTemplate::where('mailable', SendForReviewMail::class)->first();
                $form->fill([
                    'subject' => $mailTemplate ? $mailTemplate->subject : '',
                    'message' => $mailTemplate ? $mailTemplate->html_template : '',
                ]);
            })
            ->form([
                TextInput::make('review_round_name')
                    ->label(__('general.review_round_name'))
                    ->placeholder(__('general.review_round_name_placeholder'))
                    ->helperText(__('general.review_round_name_helper'))
                    ->maxLength(255),
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
                                        Action::make($paper->media->original_file_name)
                                            ->label($paper->media->original_file_name)
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
                Grid::make()
                    ->visible(fn () => ! $this->submission->payment && app()->getCurrentScheduledConference()->isSubmissionPaymentEnabled())
                    ->schema([
                        Radio::make('payment_fee_id')
                            ->label('Payment Fee')
                            ->required()
                            ->live()
                            ->options(
                                fn () => PaymentFee::type(PaymentManager::TYPE_SUBMISSION_FEE)
                                    ->active()
                                    ->get()
                                    ->mapWithKeys(fn (PaymentFee $paymentFee) => [$paymentFee->getKey() => $paymentFee->name])
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

                                return collect($paymentFee->getAdditionalItems())->map(function ($item) use ($paymentFee) {
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
                    ]),
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
                        $reviewRoundName = data_get($data, 'review_round_name');

                        $this->submission->state()->sendForReview();
                        $this->submission->refresh();

                        $reviewRound = $this->submission->activeReviewRound
                            ?? $this->submission->latestReviewRound;

                        if (
                            ! $reviewRound &&
                            $this->submission->stage === SubmissionStage::PeerReview &&
                            in_array($this->submission->status, [SubmissionStatus::OnReview, SubmissionStatus::OnPayment], true)
                        ) {
                            $reviewRound = StartSubmissionReviewRoundAction::run(
                                $this->submission,
                                [],
                                auth()->user(),
                                $reviewRoundName
                            );
                            $this->submission->refresh();
                        }

                        if ($reviewRound && filled($reviewRoundName) && blank($reviewRound->name)) {
                            $reviewRound->update([
                                'name' => trim($reviewRoundName),
                            ]);
                        }

                        $submissionFiles = $this->submission
                            ->submissionFiles()
                            ->with(['media'])
                            ->whereIn('id', data_get($data, 'papers', []))
                            ->where('category', SubmissionFileCategory::ABSTRACT_FILES)
                            ->get();

                        $clonedFileIds = [];

                        foreach ($submissionFiles as $record) {
                            $clonedMedia = $record->media->copy(
                                $this->submission,
                                SubmissionFileCategory::REVIEW_FILES,
                                'private-files'
                            );
                            $clonedFile = $this->submission
                                ->submissionFiles()
                                ->create([
                                    'review_round_id' => $reviewRound?->getKey(),
                                    'submission_file_type_id' => $record->type->getKey(),
                                    'category' => SubmissionFileCategory::REVIEW_FILES,
                                    'media_id' => $clonedMedia->getKey(),
                                ]);

                            $clonedFileIds[] = $clonedFile->getKey();
                        }

                        if ($reviewRound && $clonedFileIds !== []) {
                            $reviewRound->update([
                                'default_file_ids' => collect($reviewRound->default_file_ids ?? [])
                                    ->merge($clonedFileIds)
                                    ->unique()
                                    ->values()
                                    ->all(),
                            ]);
                        }

                        if (! $this->submission->payment && app()->getCurrentScheduledConference()->isSubmissionPaymentEnabled()) {
                            $paymentFee = PaymentFee::find(data_get($data, 'payment_fee_id'));

                            if (! $paymentFee) {
                                throw new \Exception('Payment Fee not found');
                            }

                            $additionalItems = data_get($data, 'additional_items', []);
                            $selectedAdditionalItems = $paymentFee->getSelectedAdditionalItemsFromData(['additional_items' => $additionalItems]);
                            $totalAmount = $paymentFee->getAmountWithAdditionalItemsFromData(['additional_items' => $additionalItems]);

                            PaymentManager::get()->queue(
                                $this->submission,
                                $paymentFee,
                                $this->submission->user,
                                PaymentManager::TYPE_SUBMISSION_FEE,
                                $this->submission->getMeta('title'),
                                SubmissionResource::getUrl('view', ['record' => $this->submission]),
                                $paymentFee->getMeta('description'),
                                $totalAmount,
                                $paymentFee->currency,
                                null,
                                $selectedAdditionalItems,
                                $paymentFee->amount,
                            );
                        }

                        if (! $data['no-notification']) {
                            try {
                                $this->submission->user
                                    ->notify(
                                        new SubmissionSentForReview(
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
                                new SubmissionSentForReview(
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
                        $action->failureNotificationTitle(__('general.failed_to_send_for_review'));
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
