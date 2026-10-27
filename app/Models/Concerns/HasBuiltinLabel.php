<?php

namespace App\Models\Concerns;

trait HasBuiltinLabel
{
    use HasTranslatableContent {
        translated as translatedContent;
    }

    /**
     * Default English names keyed by built-in code.
     *
     * @return array<string, string>
     */
    abstract public static function builtinNames(): array;

    public function translated(string $key, ?string $locale = null): mixed
    {
        if (! $this->isUntouchedBuiltin($key)) {
            return $this->translatedContent($key, $locale);
        }

        $locale ??= app()->getLocale();
        $stored = $this->getStoredTranslation($key, $locale);

        return static::isBlankContent($stored) || $stored === $this->builtinName()
            ? __('general.'.$this->getAttribute('builtin_code'), [], $locale)
            : $stored;
    }

    protected function isUntouchedBuiltin(string $key): bool
    {
        return $key === $this->builtinNameAttribute()
            && $this->builtinName() !== null
            && $this->getTranslationSource($key) === $this->builtinName();
    }

    protected function builtinName(): ?string
    {
        return static::builtinNames()[(string) $this->getAttribute('builtin_code')] ?? null;
    }

    protected function builtinNameAttribute(): string
    {
        return 'name';
    }
}
