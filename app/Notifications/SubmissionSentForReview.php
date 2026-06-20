<?php

namespace App\Notifications;

use App\Mail\Templates\SendForReviewMail;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SubmissionSentForReview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Submission $submission, public string $message = '', public string $subject = '', public array $channels = []) {}

    public function via($notifiable): array
    {
        if (! filled($this->channels)) {
            return ['database', 'mail'];
        }

        return $this->channels;
    }

    public function toMail($notifiable)
    {
        $mailTemplate = new SendForReviewMail($this->submission);
        if (filled($this->subject)) {
            $mailTemplate = $mailTemplate->subjectUsing($this->subject);
        }
        if (filled($this->message)) {
            $mailTemplate = $mailTemplate->contentUsing($this->message);
        }

        return $mailTemplate
            ->to($notifiable);
    }

    public function toDatabase($notifiable)
    {
        return FilamentNotification::make()
            ->icon('lineawesome-check-circle')
            ->iconColor('success')
            ->title(__('general.submission_sent_for_review'))
            ->body("Title: {$this->submission->getMeta('title')}")
            ->actions([
                Action::make('view-abstract')
                    ->url(SubmissionResource::getUrl('view', ['record' => $this->submission, 'tenant' => $this->submission->conference]))
                    ->label(__('general.view'))
                    ->markAsRead(),
            ])
            ->toDatabase();
    }
}
