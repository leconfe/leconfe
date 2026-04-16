<?php

namespace App\Notifications;

use App\Mail\Templates\SubmissionWithdrawnMail;
use App\Models\Submission;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SubmissionWithdrawn extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Submission $submission) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new SubmissionWithdrawnMail($this->submission))->to($notifiable);
    }

    public function toDatabase($notifiable)
    {
        return FilamentNotification::make()
            ->icon('lineawesome-exclamation-circle-solid')
            ->iconColor('danger')
            ->title('Submission Withdrawn')
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
