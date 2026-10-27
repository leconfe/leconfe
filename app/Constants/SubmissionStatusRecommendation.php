<?php

namespace App\Constants;

final class SubmissionStatusRecommendation
{
    public const ACCEPT = 'Accept';

    public const DECLINE = 'Decline';

    public const REVISION_REQUIRED = 'Revision Required';

    public static function list()
    {
        return [
            self::ACCEPT => self::label(self::ACCEPT),
            self::DECLINE => self::label(self::DECLINE),
            self::REVISION_REQUIRED => self::label(self::REVISION_REQUIRED),
        ];
    }

    public static function label(?string $recommendation): ?string
    {
        return match ($recommendation) {
            self::ACCEPT => __('general.recommendation_accept'),
            self::DECLINE => __('general.recommendation_decline'),
            self::REVISION_REQUIRED => __('general.revision_required'),
            default => $recommendation,
        };
    }
}
