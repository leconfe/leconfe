<?php

namespace App\Filament\Forms\Components;

use App\Facades\Setting;
use Closure;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Field;
use Illuminate\Support\Collection;

class MultilanguageComponent extends Component
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.forms.components.multilanguage';

    protected null | Closure | array | Collection $locales = null;

    protected null | Closure | string $primaryLocale = null;

    protected null | Closure | array | Collection $localeLabels = null;

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

    /**
     * @param  \Closure|array<string>|\Illuminate\Support\Collection<string>  $locales
     */
    public function locales(Closure | array | Collection $locales): static
    {
        $this->locales = $locales;

        return $this;
    }

    public function primaryLocale(string $locale)
    {
        $this->primaryLocale = $locale;

        return $this;
    }

    public function getPrimaryLocale(): string
    {
        return $this->evaluate($this->primaryLocale) ?? Setting::get('default_language', app()->getLocale());
    }

    public function localeLabels(Closure | array | Collection $labels): static
    {
        $this->localeLabels = $labels;

        return $this;
    }


    /**
     * @return array<string>|\Illuminate\Support\Collection<string>
     */
    public function getLocales(): array | Collection
    {
        $locales = $this->evaluate($this->locales) ?? Setting::get('languages', [app()->getLocale()]);
        $primaryLocale = $this->getPrimaryLocale();

        usort($locales, fn($a, $b) => ($a === $primaryLocale ? -1 : ($b === $primaryLocale ? 1 : 0)));
        return $locales;
    }

    public function getChildComponentsByLocale(string $locale): array
    {
        return collect($this->getChildComponents())
            ->map(fn($component) => $this->prepareLocaleComponent($component, $locale))
            ->all();
    }

    /**
     * @return array<Component>
     */
    public function getChildComponents(): array
    {
        return collect($this->evaluate($this->childComponents))
            ->map(function ($component) {
                return $this->prepareMultilanguageField($component);
            })
            ->all();

        return $this->evaluate($this->childComponents);
    }

    protected function prepareMultilanguageField(Component $component){
        $clonedComponent = clone $component;
        
        if ($clonedComponent instanceof Field || method_exists($clonedComponent, 'getName')) {
            return $this->makeMultilanguageField($clonedComponent);
        }

        $childComponents = $clonedComponent->getChildComponents();
        if (!empty($childComponents)) {
            $clonedComponent->schema(
                collect($childComponents)
                    ->map(fn($childComponent) => $this->prepareMultilanguageField($childComponent))
                    ->all()
            );
        }

        return $clonedComponent;
    }

    protected function makeMultilanguageField(Component $component): MultilanguageField
    {
        return MultilanguageField::make()
            ->schema(
                collect($this->getLocales())
                    ->map(fn($locale) => $this->prepareLocaleComponent($component, $locale))
                    ->all()
            );
    }

    protected function prepareLocaleComponent(Component $component, string $locale): Component
    {
        $localeComponent = clone $component;

        if ($localeComponent instanceof Field || method_exists($localeComponent, 'getName')) {

            $localeComponentName = $localeComponent->getName();
            if (filled($localeComponentName) && is_string($localeComponentName)) {

                if ($locale !== $this->getPrimaryLocale()) {
                    $localeComponent->hiddenLabel()->required(false);

                    if (method_exists($localeComponent, 'placeholder')) $localeComponent->placeholder($this->getLocaleLabel($locale));
                    if (method_exists($localeComponent, 'hint')) $localeComponent->hint(null);
                    if (method_exists($localeComponent, 'helperText')) $localeComponent->helperText(null);
                } else {
                    $localeComponent->label($component->getLabel());
                }

                if (method_exists($localeComponent, 'name')) {
                    $localeComponent->name($localeComponentName . '.' . $locale);
                }
                if (method_exists($localeComponent, 'statePath')) {
                    $localeComponent->statePath($localeComponent->getName());
                }
            }
        } else {

            $childComponents = $localeComponent->getChildComponents();

            if (!empty($childComponents)) {
                $localeComponent->schema(
                    collect($childComponents)
                        ->map(fn($childComponent) => $this->prepareLocaleComponent($childComponent, $locale))
                        ->all()
                );
            }
        }

        return $localeComponent;
    }

    public function getLocaleLabel(string $locale, ?string $displayLocale = null): ?string
    {
        $displayLocale ??= app()->getLocale();

        return locale_get_display_name($locale, $displayLocale) ?: null;
    }
}
