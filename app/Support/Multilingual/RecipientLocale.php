<?php

namespace App\Support\Multilingual;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Support\Facades\Mail;

/**
 * Resolves the language an email is written in for its recipients.
 */
class RecipientLocale
{
    /**
     * The language for a recipient: a user, an email address, an object with an
     * `email` property, or a list of those. A list whose recipients prefer different
     * languages, and anything that is not a known user, gets the site default language.
     */
    public static function for(mixed $recipient): string
    {
        $default = app(ContentLocales::class)->default();

        if ($recipient instanceof HasLocalePreference) {
            return $recipient->preferredLocale() ?: $default;
        }

        if (is_string($recipient)) {
            return static::forEmail($recipient) ?? $default;
        }

        if (is_iterable($recipient)) {
            $locales = collect($recipient)
                ->map(fn ($item) => static::for($item))
                ->unique()
                ->values();

            return $locales->count() === 1 ? $locales->first() : $default;
        }

        if (is_object($recipient) && is_string($recipient->email ?? null)) {
            return static::forEmail($recipient->email) ?? $default;
        }

        return $default;
    }

    /**
     * Send one message per language to the given users. $make builds a new mailable
     * for each language group.
     *
     * @param  iterable<User>  $users
     * @param  Closure(): Mailable  $make
     */
    public static function send(iterable $users, Closure $make): void
    {
        collect($users)
            ->groupBy(fn ($user) => static::for($user))
            ->each(fn ($group, string $locale) => Mail::to($group)->locale($locale)->send($make()));
    }

    protected static function forEmail(string $email): ?string
    {
        return User::query()
            ->where('email', mb_strtolower($email))
            ->orWhere('email', $email)
            ->first()
            ?->preferredLocale();
    }
}
