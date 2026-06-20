<?php

namespace App\Notifications;

use App\Constants\SubmissionFileCategory;
use App\Mail\Templates\NewReviewFileUploadedMail;
use App\Mail\Templates\NewRevisionUploadedMail;
use App\Models\SubmissionFile;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class SubmissionFileUploaded extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public SubmissionFile $submissionFile)
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
        return match ($this->submissionFile->category) {
            SubmissionFileCategory::REVIEW_FILES => ['mail', 'database'],
            SubmissionFileCategory::REVISION_FILES => ['mail', 'database'],
            default => [],
        };
    }

    public function toMail(object $notifiable)
    {
        $mailTempalte = match ($this->submissionFile->category) {
            SubmissionFileCategory::REVIEW_FILES => NewReviewFileUploadedMail::class,
            SubmissionFileCategory::REVISION_FILES => NewRevisionUploadedMail::class,
            default => null
        };

        if (! $mailTempalte) {
            return null;
        }

        return (new $mailTempalte($this->submissionFile))
            ->to($notifiable);
    }

    public function toDatabase(object $notifiable)
    {
        return FilamentNotification::make()
            ->icon('lineawesome-exclamation-circle-solid')
            ->iconColor('primary')
            ->title(function () {
                return match ($this->submissionFile->category) {
                    SubmissionFileCategory::REVIEW_FILES => __('general.review_file_uploaded'),
                    SubmissionFileCategory::REVISION_FILES => 'New Revision Uploaded',
                    default => 'New File Uploaded'
                };
            })
            ->body("Title: {$this->submissionFile->submission->getMeta('title')}")
            ->actions([
                Action::make('new-submission')
                    ->url($this->resolveSubmissionUrl())
                    ->label(__('general.view'))
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

    protected function resolveSubmissionUrl(): string
    {
        try {
            return SubmissionResource::getUrl('view', [
                'record' => $this->submissionFile->submission,
                'tenant' => $this->submissionFile->submission->conference,
            ]);
        } catch (RouteNotFoundException|UrlGenerationException) {
            return url('/');
        }
    }
}
