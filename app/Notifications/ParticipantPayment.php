<?php

namespace App\Notifications;

use App\Mail\Templates\ParticipantPaymentMail;
use App\Mail\Templates\PaymentRequiredMail;
use App\Mail\Templates\SubmissionPaymentMail;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\Submission;
use App\Panel\ScheduledConference\Pages\PaymentDetail;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ParticipantPayment extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Participant $participant,
        )
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable)
    {
        return (new ParticipantPaymentMail($this->participant))
            ->to($notifiable);
    }

    public function toDatabase(object $notifiable)
    {
        return FilamentNotification::make()
            ->icon('lineawesome-exclamation-circle-solid')
            ->iconColor('primary')
            ->title('Payment Required')
            ->body("Participant Payment for: " . $this->participant->payment->fee->name)
            ->actions([
                Action::make('new-submission')
                    ->url(PaymentDetail::getUrl(['record' => $this->participant->payment]))
                    ->label('Pay')
                    ->openUrlInNewTab()
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
