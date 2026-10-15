<?php

namespace App\Filament\Forms\Components;

use App\Support\Multilingual\ContentLocales;
use Closure;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Field;

/**
 * Renders the wrapped fields once per enabled content language.
 *
 * Each field's state becomes an array keyed by locale (`name.en`, `name.id`). With a
 * single enabled language the wrapped fields are rendered unchanged.
 */
class MultilanguageComponent extends Component
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.forms.components.multilanguage';

    /**
     * @var array<int, string> | Closure | null
     */
    protected array|Closure|null $locales = null;

    protected string|Closure|null $primaryLocale = null;

    /**
     * @var array<Component> | null
     */
    protected ?array $preparedChildComponents = null;

    final public function __construct(array|Closure $schema = [])
    {
        $this->schema($schema);
    }

    public static function make(array|Closure $schema = []): static
    {
        $static = app(static::class, ['schema' => $schema]);
        $static->configure();

        return $static;
    }

    /**
     * @param  array<int, string> | Closure  $locales
     */
    public function locales(array|Closure $locales): static
    {
        $this->locales = $locales;
        $this->preparedChildComponents = null;

        return $this;
    }

    public function primaryLocale(string|Closure $locale): static
    {
        $this->primaryLocale = $locale;
        $this->preparedChildComponents = null;

        return $this;
    }

    public function getPrimaryLocale(): string
    {
        return $this->evaluate($this->primaryLocale) ?? app(ContentLocales::class)->default();
    }

    /**
     * @return array<int, string>
     */
    public function getLocales(): array
    {
        $primaryLocale = $this->getPrimaryLocale();
        $locales = $this->evaluate($this->locales) ?? app(ContentLocales::class)->ordered();

        return array_values(array_unique([$primaryLocale, ...$locales]));
    }

    public function isMultilingual(): bool
    {
        return count($this->getLocales()) > 1;
    }

    /**
     * @return array<Component>
     */
    public function getChildComponents(): array
    {
        $childComponents = $this->evaluate($this->childComponents);

        if (! $this->isMultilingual()) {
            return $childComponents;
        }

        return $this->preparedChildComponents ??= collect($childComponents)
            ->map(fn (Component $component) => $this->prepareMultilanguageComponent($component))
            ->all();
    }

    public function getLocaleLabel(string $locale): string
    {
        return app(ContentLocales::class)->label($locale);
    }

    protected function prepareMultilanguageComponent(Component $component): Component
    {
        if ($component instanceof Field) {
            return MultilanguageField::make(
                collect($this->getLocales())
                    ->map(fn (string $locale) => $this->prepareLocaleField($component, $locale))
                    ->all()
            )
                ->primaryLocale($this->getPrimaryLocale())
                ->columnSpan($component->getColumnSpan());
        }

        $clonedComponent = clone $component;
        $childComponents = $clonedComponent->getChildComponents();

        if (! empty($childComponents)) {
            $clonedComponent->schema(
                collect($childComponents)
                    ->map(fn (Component $childComponent) => $this->prepareMultilanguageComponent($childComponent))
                    ->all()
            );
        }

        return $clonedComponent;
    }

    protected function prepareLocaleField(Field $field, string $locale): Field
    {
        $localeField = clone $field;
        $name = $field->getName();
        $isPrimary = $locale === $this->getPrimaryLocale();
        $direction = app(ContentLocales::class)->direction($locale);

        $localeField
            ->name("{$name}.{$locale}")
            ->statePath("{$name}.{$locale}")
            ->label(__('general.translation_in', [
                'label' => $field->getLabel(),
                'language' => $this->getLocaleLabel($locale),
            ]))
            ->meta('locale', $locale);

        if (method_exists($localeField, 'extraInputAttributes')) {
            $localeField->extraInputAttributes(['lang' => $locale, 'dir' => $direction], merge: true);
        }

        if (method_exists($localeField, 'direction')) {
            $localeField->direction($direction);
        }

        $localeField->extraFieldWrapperAttributes(['lang' => $locale, 'dir' => $direction], merge: true);

        if ($isPrimary) {
            return $localeField;
        }

        $localeField->required(false);

        // Rules such as `required` only apply to the default language.
        (function () {
            $this->rules = array_values(array_filter(
                $this->rules,
                fn (array $rule) => ! (is_string($rule[0]) && str_starts_with($rule[0], 'required')),
            ));
        })->call($localeField);

        if (method_exists($localeField, 'autofocus')) {
            $localeField->autofocus(false);
        }

        $localeField->hint(null)->helperText(null);

        return $localeField;
    }
}
