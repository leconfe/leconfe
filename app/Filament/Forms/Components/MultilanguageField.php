<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Field;

/**
 * Groups the per-locale copies of one field. The default-language input comes first.
 */
class MultilanguageField extends Component
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.forms.components.multilanguage-field';

    protected ?string $primaryLocale = null;

    final public function __construct(array $schema = [])
    {
        $this->schema($schema);
    }

    public static function make(array $schema = []): static
    {
        $static = app(static::class, ['schema' => $schema]);
        $static->configure();

        return $static;
    }

    public function primaryLocale(string $locale): static
    {
        $this->primaryLocale = $locale;

        return $this;
    }

    public function getChildComponentContainers(bool $withHidden = false): array
    {
        if (! $this->hasChildComponentContainer($withHidden)) {
            return [];
        }

        return collect($this->getChildComponents())
            ->map(fn (Component $component) => ComponentContainer::make($this->getLivewire())
                ->parentComponent($this)
                ->components([$component]))
            ->all();
    }

    /**
     * Absolute state paths of the inputs for the non-default languages.
     *
     * @return array<int, string>
     */
    public function getTranslationStatePaths(): array
    {
        return collect($this->getChildComponentContainers())
            ->skip(1)
            ->flatMap(fn (ComponentContainer $container) => $container->getComponents())
            ->filter(fn (Component $component) => $component instanceof Field)
            ->map(fn (Field $component) => $component->getStatePath())
            ->values()
            ->all();
    }
}
