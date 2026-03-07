<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Field;

class AddOnItemCounter extends Field
{
    protected string $view = 'forms.components.addon-item-counter';

    protected ?string $currency = null;

    protected int $minValue = 0;

    protected int $maxValue = 999;

    protected int $step = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(0);
    }

    public function currency(?string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function minValue(int $value): static
    {
        $this->minValue = $value;

        return $this;
    }

    public function getMinValue(): int
    {
        return $this->minValue;
    }

    public function maxValue(int $value): static
    {
        $this->maxValue = $value;

        return $this;
    }

    public function getMaxValue(): int
    {
        return $this->maxValue;
    }

    public function step(int $value): static
    {
        $this->step = $value;

        return $this;
    }

    public function getStep(): int
    {
        return $this->step;
    }
}
