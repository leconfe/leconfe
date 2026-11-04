<?php

namespace App\Services\EmailPreferences;

use App\Mail\Templates;

final class Categories
{
    public const ANNOUNCEMENT = 'announcement';

    /**
     * Operational email templates users can mute per scheduled conference.
     * Categories are identified by mailable class, never by editable subject.
     */
    public const MAILS = [
        Templates\NewSubmissionMail::class => 'new_submission',
        Templates\ParticipantRegisteredMail::class => 'participant_registered',
        Templates\ReviewCompleteMail::class => 'review_completed',
        Templates\NewRevisionUploadedMail::class => 'revision_uploaded',
        Templates\NewReviewFileUploadedMail::class => 'review_file_uploaded',
        Templates\UserPayPaymentMail::class => 'payment_proof',
        Templates\SubmissionWithdrawnRequestMail::class => 'withdrawal_requested',
        Templates\NewAnnouncementMail::class => self::ANNOUNCEMENT,
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_values(self::MAILS);
    }

    public static function forMail(string $class): ?string
    {
        foreach (self::MAILS as $template => $category) {
            if (is_a($class, $template, true)) {
                return $category;
            }
        }

        return null;
    }
}
