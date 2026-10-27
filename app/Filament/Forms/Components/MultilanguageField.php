<?php

namespace App\Filament\Forms\Components;

use App\Support\Multilingual\ContentLocales;
use App\Support\Multilingual\TranslationStatus;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Field;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

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

    public function getTranslationsId(): string
    {
        return 'translations-'.md5(implode('|', $this->getTranslationStatePaths()));
    }

    public function getTranslationsLabel(): HtmlString
    {
        $paths = $this->getTranslationStatePaths();
        $livewire = $this->getLivewire();

        return new HtmlString(view('filament.forms.components.multilanguage-field-toggle', [
            'filledCount' => collect($paths)->reject(fn (string $path) => ContentLocales::isBlank(data_get($livewire, $path)))->count(),
            'total' => count($paths),
        ])->render());
    }

    /**
     * Absolute state paths of the inputs for the non-default languages.
     *
     * @return array<int, string>
     */
    public function getTranslationStatePaths(): array
    {
        return $this->getLocaleFields()
            ->skip(1)
            ->map(fn (Field $component) => $component->getStatePath())
            ->values()
            ->all();
    }

    public function getPrimaryStatePath(): ?string
    {
        return $this->getLocaleFields()->first()?->getStatePath();
    }

    public function getPrimaryLanguage(): string
    {
        return app(ContentLocales::class)->label($this->primaryLocale ?? app(ContentLocales::class)->default());
    }

    /**
     * @return array<int, array{path: string, language: string, status: TranslationStatus}>
     */
    public function getTranslationStatuses(): array
    {
        $livewire = $this->getLivewire();
        $primary = data_get($livewire, $this->getPrimaryStatePath());

        return $this->getLocaleFields()
            ->skip(1)
            ->map(fn (Field $component) => [
                'path' => $component->getStatePath(),
                'language' => app(ContentLocales::class)->label($component->getMeta('locale')),
                'status' => TranslationStatus::of(data_get($livewire, $component->getStatePath()), $primary),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Field>
     */
    protected function getLocaleFields(): Collection
    {
        return collect($this->getChildComponentContainers())
            ->flatMap(fn (ComponentContainer $container) => $container->getComponents())
            ->filter(fn (Component $component) => $component instanceof Field)
            ->values();
    }
}
