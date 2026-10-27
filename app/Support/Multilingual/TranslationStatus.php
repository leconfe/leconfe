<?php

namespace App\Support\Multilingual;

/**
 * State of one language copy of a field. A blank copy shows the default-language
 * text when that text exists, otherwise nothing.
 */
enum TranslationStatus: string
{
    case Complete = 'complete';
    case Fallback = 'fallback';
    case Missing = 'missing';
    case Invalid = 'invalid';

    public static function of(mixed $translation, mixed $primary, bool $hasError = false): self
    {
        return match (true) {
            $hasError => self::Invalid,
            ! ContentLocales::isBlank($translation) => self::Complete,
            ! ContentLocales::isBlank($primary) => self::Fallback,
            default => self::Missing,
        };
    }

    public function label(string $primaryLanguage): string
    {
        return match ($this) {
            self::Complete => __('general.translation_complete'),
            self::Fallback => __('general.translation_fallback', ['language' => $primaryLanguage]),
            self::Missing => __('general.translation_missing'),
            self::Invalid => __('general.translation_invalid'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Complete => 'heroicon-m-check-circle',
            self::Fallback => 'heroicon-m-arrow-uturn-left',
            self::Missing => 'heroicon-m-exclamation-circle',
            self::Invalid => 'heroicon-m-x-circle',
        };
    }

    public function textClasses(): string
    {
        return match ($this) {
            self::Complete => 'text-success-600 dark:text-success-400',
            self::Fallback => 'text-warning-600 dark:text-warning-400',
            self::Missing, self::Invalid => 'text-danger-600 dark:text-danger-400',
        };
    }
}
