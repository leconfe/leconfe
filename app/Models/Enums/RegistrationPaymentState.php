<?php

namespace App\Models\Enums;

use App\Models\Enums\Concern\UsefulEnums;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * @deprecated deprecated since version 1.2.0
 */
enum RegistrationPaymentState: string implements HasColor, HasLabel
{
    use UsefulEnums;

    case Paid = 'Paid';
    case Unpaid = 'Unpaid';

    public function getLabel(): ?string
    {
        return $this->name;
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Paid => Color::Green,
            self::Unpaid => Color::Yellow,
        };
    }
}
