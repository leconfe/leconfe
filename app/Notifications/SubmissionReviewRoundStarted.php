<?php

namespace App\Notifications;

use App\Mail\Templates\ReviewRoundStartedMail;
use App\Models\Submission;
use App\Models\SubmissionReviewRound;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SubmissionReviewRoundStarted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Submission $submission,
        public SubmissionReviewRound $reviewRound,
        public string $message = '',
        public string $subject = '',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): ReviewRoundStartedMail
    {
        $mailTemplate = new ReviewRoundStartedMail($this->submission, $this->reviewRound);

        if (filled($this->subject)) {
            $mailTemplate = $mailTemplate->subjectUsing($this->subject);
        }

        if (filled($this->message)) {
            $mailTemplate = $mailTemplate->contentUsing($this->message);
        }

        return $mailTemplate->to($notifiable);
    }

    public function toDatabase(object $notifiable)
    {
        return FilamentNotification::make()
            ->icon('lineawesome-sync-alt-solid')
            ->iconColor('primary')
            ->title(__('general.sent_for_a_new_round_of_reviews'))
            ->body("Title: {$this->submission->getMeta('title')}")
            ->actions([
                Action::make('view-submission')
                    ->url(SubmissionResource::getUrl('view', ['record' => $this->submission, 'tenant' => $this->submission->conference]))
                    ->label(__('general.view'))
                    ->markAsRead(),
            ])
            ->toDatabase();
    }
}
