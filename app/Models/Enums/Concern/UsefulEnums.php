<?php

namespace App\Models\Enums\Concern;

trait UsefulEnums
{
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function array(): array
    {
        return array_combine(self::names(), self::values());
    }

    public static function random(): static
    {
        return self::from(self::values()[array_rand(self::values())]);
    }

    public static function options(): array
    {
        return array_combine(self::values(), self::names());
    }

    public function isOneOf(mixed ...$values): bool
    {
        return in_array($this, $values);
    }

    public static function fromName(string $name): static
    {
        if ($status = self::tryFromName($name)) {
            return $status;
        }
        throw new \ValueError("$name is not a valid backing value for enum ".self::class);
    }

    public static function tryFromName(string $name): ?static
    {
        foreach (self::cases() as $status) {
            if ($name === $status->name) {
                return $status;
            }
        }

        return null;
    }

    public function is($value): bool
    {
        // Check if the value is an instance of the enum
        if ($value instanceof static) {
            return $this->value === $value->value;
        }

        return $this->value === $value;
    }
}
