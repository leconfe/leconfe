<?php

namespace App\Notifications;

use App\Mail\Templates\PaymentRequiredMail;
use App\Models\Payment;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentRequired extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Payment $payment)
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
        return (new PaymentRequiredMail($this->payment))
            ->to($notifiable);
    }

    public function toDatabase(object $notifiable)
    {
        return FilamentNotification::make()
            ->icon('lineawesome-exclamation-circle-solid')
            ->iconColor('primary')
            ->title('Payment Required')
            ->body('Title: '.$this->payment->getMeta('title'))
            ->actions([
                Action::make('new-submission')
                    ->url($this->payment->getPaymentUrl())
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
