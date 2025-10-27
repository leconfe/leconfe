<?php

namespace App\Notifications;

use App\Mail\Templates\PaymentConfirmedMail;
use App\Models\Payment;
use App\Panel\ScheduledConference\Pages\PaymentDetail;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Payment $payment,
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
        return (new PaymentConfirmedMail($this->payment))
            ->to($notifiable);
    }

    public function toDatabase(object $notifiable)
    {
        return FilamentNotification::make()
            ->icon('lineawesome-exclamation-circle-solid')
            ->iconColor('primary')
            ->title('Payment Confirmed')
            ->actions([
                Action::make('payment-confirmed')
                    ->url(PaymentDetail::getUrl(['record' => $this->payment]))
                    ->label('Payment Detail')
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
