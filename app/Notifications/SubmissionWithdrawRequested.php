<?php

namespace App\Notifications;

use App\Mail\Templates\SubmissionWithdrawnRequestMail;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SubmissionWithdrawRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public $submission) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new SubmissionWithdrawnRequestMail($this->submission))->to($notifiable);
    }

    public function toDatabase($notifiable)
    {
        return FilamentNotification::make()
            ->icon('lineawesome-exclamation-circle-solid')
            ->iconColor('danger')
            ->title('Withdrawal Request')
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
