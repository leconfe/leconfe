<?php

namespace App\Models\Enums;

use App\Models\Enums\Concern\UsefulEnums;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubmissionStatus: string implements HasColor, HasLabel
{
    use UsefulEnums;

    case Incomplete = 'Incomplete';
    case Queued = 'Queued';
    case OnReview = 'On Review';
    /**
     * @deprecated
     */
    case OnPayment = 'On Payment';
    case OnPresentation = 'On Presentation';
    case Editing = 'Editing';
    case Published = 'Published';
    /**
     * @deprecated
     */
    case PaymentDeclined = 'Payment Declined';
    case Declined = 'Declined';
    case Withdrawn = 'Withdrawn';

    public function getLabel(): ?string
    {
        return $this->name;
    }

    public function getOrder(): int
    {
        return match ($this) {
            self::Incomplete => 1,
            self::Queued => 2,
            self::OnReview => 3,
            self::OnPayment => 4,
            self::OnPresentation => 5,
            self::Editing => 6,
            self::Published => 7,
            self::PaymentDeclined => 8,
            self::Declined => -90,
            self::Withdrawn => -80,
        };
    }

    public function isBefore(SubmissionStatus $status): bool
    {
        return $this->getOrder() < $status->getOrder();
    }

    public function isAfter(SubmissionStatus $status): bool
    {
        return $this->getOrder() > $status->getOrder();
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Published, self::PaymentDeclined, self::Declined, self::Withdrawn]);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Declined, self::Withdrawn, self::PaymentDeclined => 'danger',
            self::OnReview, self::OnPayment => 'warning',
            self::Queued => 'primary',
            self::Editing, self::OnPresentation => 'info',
            self::Published => 'success',
            default => 'gray'
        };
    }
}
