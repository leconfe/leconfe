<?php

namespace App\Notifications;

use App\Constants\ReviewerStatus;
use App\Mail\Templates\NewDiscussionTopicMail;
use App\Models\DiscussionTopic;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewDiscussionTopic extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DiscussionTopic $topic, public array $channels = []) {}

    public function via($notifiable): array
    {
        if (! filled($this->channels)) {
            return ['database', 'mail'];
        }

        return $this->channels;
    }

    public function toMail($notifiable)
    {
        return (new NewDiscussionTopicMail($this->topic, $this->getSubmissionUrl($notifiable)))
            ->to($notifiable);
    }

    public function toDatabase($notifiable)
    {
        return FilamentNotification::make()
            ->icon('lineawesome-check-circle')
            ->iconColor('success')
            ->title(__('general.new_discussion_topic_created'))
            ->body("Topic: {$this->topic->name}")
            ->actions([
                Action::make('view-submission')
                    ->url($this->getSubmissionUrl($notifiable))
                    ->label(__('general.view'))
                    ->markAsRead(),
            ])
            ->toDatabase();
    }

    private function getSubmissionUrl($notifiable): string
    {
        $submission = $this->topic->submission;
        $review = $submission->getReviewForUserInActiveRound($notifiable);

        if (! $review) {
            return SubmissionResource::getUrl('view', ['record' => $submission]);
        }

        $page = $review->needConfirmation() || $review->status === ReviewerStatus::DECLINED
            ? 'reviewer-invitation'
            : 'review';

        return SubmissionResource::getUrl($page, ['record' => $submission]);
    }
}
