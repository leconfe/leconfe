<?php

namespace App\Support\Multilingual;

use App\Models\MailTemplate;
use App\Models\User;
use Filament\Forms\Get;
use Filament\Forms\Set;

/**
 * Keeps a pre-filled email subject and message in the language of the user it is
 * sent to, while the sender has not edited them.
 */
class PrefilledEmail
{
    /**
     * Replace the subject and message pre-filled for the previous recipient with the
     * template in the new recipient's language. Text the sender has edited stays.
     *
     * @param  class-string<\App\Mail\Templates\TemplateMailable>  $mailable
     */
    public static function refill(
        string $mailable,
        Get $get,
        Set $set,
        mixed $previousUserId,
        mixed $userId,
        string $subjectPath = 'subject',
        string $messagePath = 'message',
    ): void {
        $previous = MailTemplate::contentFor($mailable, static::localeForUser($previousUserId));
        $next = MailTemplate::contentFor($mailable, static::localeForUser($userId));

        if (static::matches($get($subjectPath), $previous['subject'])) {
            $set($subjectPath, $next['subject']);
        }

        if (static::matches($get($messagePath), $previous['html_template'])) {
            $set($messagePath, $next['html_template']);
        }
    }

    protected static function localeForUser(mixed $userId): ?string
    {
        if (blank($userId)) {
            return null;
        }

        $user = User::query()->find($userId);

        return $user ? RecipientLocale::for($user) : null;
    }

    /**
     * Whether form text still equals the pre-filled text, ignoring markup and
     * whitespace changes made by the editor.
     */
    protected static function matches(mixed $value, ?string $prefilled): bool
    {
        return static::normalize($value) === static::normalize($prefilled);
    }

    protected static function normalize(mixed $value): string
    {
        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $text)));
    }
}
