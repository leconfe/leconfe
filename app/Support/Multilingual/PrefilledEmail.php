<?php

namespace App\Support\Multilingual;

use App\Models\MailTemplate;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
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
     * Whether form text still equals the pre-filled text. Whitespace, entity and
     * attribute-order differences made by the editor are ignored, while changes to
     * text, markup or attribute values such as link targets count as edits.
     */
    protected static function matches(mixed $value, ?string $prefilled): bool
    {
        return static::normalize($value) === static::normalize($prefilled);
    }

    protected static function normalize(mixed $value): string
    {
        $html = trim((string) $value);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<?xml encoding="UTF-8"><div>'.$html.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->getElementsByTagName('div')->item(0);

        return $root ? trim(preg_replace('/\s+/u', ' ', static::serializeNodes($root))) : '';
    }

    protected static function serializeNodes(DOMNode $parent): string
    {
        $output = '';

        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMText) {
                $output .= str_replace("\u{00A0}", ' ', $node->textContent);

                continue;
            }

            if (! $node instanceof DOMElement) {
                continue;
            }

            $attributes = [];

            foreach ($node->attributes as $attribute) {
                if (! str_starts_with($attribute->name, 'data-mce-')) {
                    $attributes[$attribute->name] = trim(preg_replace('/\s+/u', ' ', $attribute->value));
                }
            }

            ksort($attributes);

            $output .= ' <'.strtolower($node->tagName).' '.json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'> ';
            $output .= static::serializeNodes($node);
            $output .= ' </'.strtolower($node->tagName).'> ';
        }

        return $output;
    }
}
