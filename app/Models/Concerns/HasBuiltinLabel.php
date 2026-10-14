<?php

namespace App\Models\Concerns;

trait HasBuiltinLabel
{
    public function getDisplayNameAttribute(): string
    {
        $name = $this->getAttribute($this->builtinNameAttribute());
        $code = $this->getAttribute('builtin_code');
        $default = static::builtinNames()[$code] ?? null;

        // A renamed built-in is organizer-owned text, just like an uncoded record.
        return $default !== null && $name === $default
            ? __('general.'.$code)
            : (string) $name;
    }

    protected function builtinNameAttribute(): string
    {
        return 'name';
    }
}
