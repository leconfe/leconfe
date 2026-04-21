<?php

namespace App\Models\Enums;

use App\Models\Enums\Concern\UsefulEnums;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

enum PresentationType: int implements HasLabel
{
    use UsefulEnums;

    case PDF = 1;
    case Youtube = 2;
    case GoogleSlide = 3;
    case Other = 4;
    // case Image = 4;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PDF => 'PDF',
            self::Youtube => 'Youtube',
            self::GoogleSlide => 'Google Slide',
            self::Other => 'Other',
        };
    }
}
