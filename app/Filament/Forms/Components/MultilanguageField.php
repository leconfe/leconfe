<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Component;

class MultilanguageField extends Component
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.forms.components.multilanguage-field';

    public static function make(array $schema = []): static
    {
        $static = app(static::class, ['schema' => $schema]);
        $static->configure();

        return $static;
    }

    public function getChildComponentContainers(bool $withHidden = false): array
    {
        if (! $this->hasChildComponentContainer($withHidden)) {
            return [];
        }

        return collect($this->getChildComponents())
            ->map(fn(Component $component) => ComponentContainer::make($this->getLivewire())
            ->parentComponent($this)
            ->components([$component]))
            ->all();
    }
}