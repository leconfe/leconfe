<?php

namespace App\Livewire;

use App\Support\Multilingual\ContentLocales;
use App\Support\SafeRedirect;
use Livewire\Component;

class LanguageSwitcher extends Component
{
    public function switchLanguage($lang)
    {
        if (! app(ContentLocales::class)->isEnabled($lang)) {
            abort(404);
        }

        session()->put('locale', $lang);
        auth()->user()?->rememberPreferredLocale($lang);

        return redirect(SafeRedirect::backUrl(request(), $this->getFallbackUrl()));
    }

    protected function getFallbackUrl(): string
    {
        try {
            return filament()->getCurrentPanel()?->getUrl() ?? url('/');
        } catch (\Throwable) {
            return url('/');
        }
    }

    public function render()
    {
        $locales = app(ContentLocales::class);

        $languages = collect($locales->enabled())
            ->mapWithKeys(fn (string $locale) => [$locale => $locales->autonym($locale)])
            ->all();

        return view('livewire.language-switcher', [
            'languages' => $languages,
        ]);
    }
}
